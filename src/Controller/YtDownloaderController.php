<?php
// src/Controller/YtDownloaderController.php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class YtDownloaderController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/yt-downloader', name: 'app_yt_downloader')]
    public function index(): Response
    {
        return $this->render('yt-downloader/index.html.twig');
    }

    #[Route('/yt-downloader/get-video-info', name: 'get_video_info', methods: ['POST'])]
    public function getVideoInfo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? '';

        if (!$url) {
            return new JsonResponse(['success' => false, 'message' => 'URL invalide.']);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $pythonPath = $projectDir . '/venv/Scripts/python.exe';
        $scriptPath = $projectDir . '/templates/yt-downloader/scripts/downloader.py';
        $outputDir = $projectDir . '/public/images/cache/';

        // Vérifier l'existence de l'exécutable Python
        if (!file_exists($pythonPath)) {
            $this->logger->error('Python executable not found at: ' . $pythonPath);
            return new JsonResponse([
                'success' => false,
                'message' => 'Python executable not found.',
            ]);
        }

        // Vérifier l'existence du script Python
        if (!file_exists($scriptPath)) {
            $this->logger->error('Python script not found at: ' . $scriptPath);
            return new JsonResponse([
                'success' => false,
                'message' => 'Python script not found.',
            ]);
        }

        // Exécuter le script Python pour obtenir les informations de la vidéo
        $process = new Process([$pythonPath, $scriptPath, '--info', $url, $outputDir]);
        $process->run();

        // Capturer la sortie et les erreurs du processus
        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        $this->logger->info('Output du script : ' . $output);
        $this->logger->error('Erreur du script : ' . $errorOutput);

        if (!$process->isSuccessful()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'exécution du script.',
                'error' => $errorOutput,
            ]);
        }

        // Décoder la sortie JSON
        try {
            $videoInfo = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Erreur de décodage JSON : ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Impossible de décoder la sortie du script.',
                'error' => $output,
            ]);
        }

        // Vérifier s'il y a une erreur dans les informations vidéo
        if (isset($videoInfo['error'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations de la vidéo.',
                'error' => $videoInfo['error'],
            ]);
        }

        // Générer l'URL publique de la miniature
        $thumbnailPath = $videoInfo['thumbnail'] ?? null;
        $thumbnailUrl = $thumbnailPath ? $request->getSchemeAndHttpHost() . '/images/cache/' . basename($thumbnailPath) : null;

        return new JsonResponse([
            'success' => true,
            'title' => $videoInfo['title'] ?? 'Titre non disponible',
            'thumbnail' => $thumbnailUrl
        ]);
    }

    #[Route('/yt-downloader/download-video', name: 'download_video', methods: ['POST'])]
    public function downloadVideo(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? '';
        $format = $data['format'] ?? 'mp4';
        $title = $data['title'] ?? 'video';

        if (!$url) {
            return new Response('URL invalide.', 400);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $safeTitle = preg_replace('/[^A-Za-z0-9 _-]/', '', $title);
        $outputFile = $projectDir . '/public/videos/' . $safeTitle . " [$format].$format";

        $pythonPath = $projectDir . '/venv/Scripts/python.exe';
        $scriptPath = $projectDir . '/templates/yt-downloader/scripts/downloader.py';

        // Vérifier l'existence de l'exécutable Python
        if (!file_exists($pythonPath)) {
            $this->logger->error('Python executable not found at: ' . $pythonPath);
            return new Response('Python executable not found.', 500);
        }

        // Vérifier l'existence du script Python
        if (!file_exists($scriptPath)) {
            $this->logger->error('Python script not found at: ' . $scriptPath);
            return new Response('Python script not found.', 500);
        }

        // Exécuter le script Python pour télécharger la vidéo
        $process = new Process([$pythonPath, $scriptPath, '--download', $url, $outputFile, $format]);
        $process->run();

        // Capturer les erreurs du processus
        $errorOutput = $process->getErrorOutput();

        $this->logger->info('Output du script : ' . $process->getOutput());
        $this->logger->error('Erreur du script : ' . $errorOutput);

        if (!$process->isSuccessful() || !file_exists($outputFile)) {
            return new Response('Erreur lors du téléchargement de la vidéo : ' . $errorOutput, 500);
        }

        // Retourner le fichier pour téléchargement et supprimer le fichier après l'envoi
        return $this->file($outputFile, basename($outputFile), ResponseHeaderBag::DISPOSITION_ATTACHMENT)
            ->deleteFileAfterSend(true);
    }
}
