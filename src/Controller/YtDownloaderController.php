<?php
// src/Controller/YtDownloaderController.php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        return $this->render('yt-downloader/index.html.twig', [
            'controller_name' => 'YtDownloaderController',
        ]);
    }

    #[Route('/yt-downloader/get-video-info', name: 'get_video_info', methods: ['POST'])]
    public function getVideoInfo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? '';

        if (!$url) {
            return new JsonResponse(['success' => false, 'message' => 'URL invalide.']);
        }

        // Normaliser l'URL YouTube
        $normalizedUrl = $this->normalizeYouTubeUrl($url);

        if (!$normalizedUrl) {
            return new JsonResponse(['success' => false, 'message' => 'URL YouTube invalide ou non supportée.']);
        }

        // Vider le cache des miniatures avant la nouvelle recherche
        $this->clearThumbnailCache();

        // Obtenir le chemin absolu du répertoire du projet
        $projectDir = $this->getParameter('kernel.project_dir');

        // Chemin vers l'exécutable Python intégré
        $pythonPath = $projectDir . '/venv/Scripts/python.exe';

        // Chemin vers le script Python
        $scriptPath = $projectDir . '/templates/yt-downloader/scripts/yt-downloader.py';

        // Vérifier que l'exécutable Python existe
        if (!file_exists($pythonPath)) {
            $this->logger->error('Python executable not found at: ' . $pythonPath);
            return new JsonResponse([
                'success' => false,
                'message' => 'Python executable not found.',
            ]);
        }

        // Vérifier que le script Python existe
        if (!file_exists($scriptPath)) {
            $this->logger->error('Python script not found at: ' . $scriptPath);
            return new JsonResponse([
                'success' => false,
                'message' => 'Python script not found.',
            ]);
        }

        // Initialiser le processus Python
        $process = new Process([$pythonPath, $scriptPath, $normalizedUrl]);
        $process->run();

        // Récupérer les sorties pour le débogage
        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        $this->logger->info('Python path: ' . $pythonPath);
        $this->logger->info('Script path: ' . $scriptPath);
        $this->logger->info('Process output: ' . $output);
        $this->logger->error('Process error output: ' . $errorOutput);

        if (!$process->isSuccessful()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'exécution du script.',
                'error' => $errorOutput,
            ]);
        }

        $videoInfo = json_decode($output, true);

        if (!$videoInfo) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Impossible de décoder la sortie du script.',
                'error' => $output,
            ]);
        }

        // Construire l'URL publique de la miniature
        $thumbnailFilename = $videoInfo['thumbnail'];
        $thumbnailUrl = $thumbnailFilename ? $request->getBasePath() . '/images/cache/' . $thumbnailFilename : null;

        return new JsonResponse([
            'success' => true,
            'thumbnail' => $thumbnailUrl,
            'title' => $videoInfo['title']
        ]);
    }

    #[Route('/yt-downloader/download-video', name: 'download_video')]
    public function downloadVideo(Request $request): Response
    {
        $url = $request->query->get('url');

        if (!$url) {
            return new Response('URL invalide.', 400);
        }

        // Normaliser l'URL YouTube
        $normalizedUrl = $this->normalizeYouTubeUrl($url);

        if (!$normalizedUrl) {
            return new Response('URL YouTube invalide ou non supportée.', 400);
        }

        // Définir un chemin de fichier temporaire
        $tempDir = sys_get_temp_dir();
        $filename = uniqid('video_') . '.mp4';
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . $filename;

        // Obtenir le chemin absolu du répertoire du projet
        $projectDir = $this->getParameter('kernel.project_dir');

        // Chemin vers l'exécutable Python intégré
        $pythonPath = $projectDir . '/venv/Scripts/python.exe';

        // Chemin vers le script Python
        $scriptPath = $projectDir . '/templates/yt-downloader/scripts/yt-downloader.py';

        // Vérifier que l'exécutable Python existe
        if (!file_exists($pythonPath)) {
            $this->logger->error('Python executable not found at: ' . $pythonPath);
            return new Response('Python executable not found.', 500);
        }

        // Vérifier que le script Python existe
        if (!file_exists($scriptPath)) {
            $this->logger->error('Python script not found at: ' . $scriptPath);
            return new Response('Python script not found.', 500);
        }

        // Initialiser le processus Python pour télécharger la vidéo
        $process = new Process([$pythonPath, $scriptPath, '--download', $normalizedUrl, $tempFile]);
        $process->run();

        if (!$process->isSuccessful() || !file_exists($tempFile)) {
            $errorOutput = $process->getErrorOutput();
            return new Response('Erreur lors du téléchargement de la vidéo : ' . $errorOutput, 500);
        }

        // Retourner le fichier en réponse pour le téléchargement
        return $this->file(
            $tempFile,
            basename($tempFile),
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        )->deleteFileAfterSend(true);
    }

    /**
     * Vider le cache des miniatures.
     */
    private function clearThumbnailCache()
    {
        // Obtenir le chemin absolu du répertoire de cache des miniatures
        $projectDir = $this->getParameter('kernel.project_dir');
        $cacheDir = $projectDir . '/public/images/cache/';

        $filesystem = new Filesystem();

        try {
            if ($filesystem->exists($cacheDir)) {
                // Supprimer les fichiers à l'intérieur du répertoire de cache
                $files = glob($cacheDir . '*');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $filesystem->remove($file);
                        }
                    }
                }
            }
        } catch (IOExceptionInterface $exception) {
            $this->logger->error("Erreur lors de la suppression du cache des miniatures : " . $exception->getMessage());
            // Vous pouvez gérer l'erreur selon vos besoins, par exemple en renvoyant une réponse d'erreur
        }
    }

    /**
     * Normaliser les URLs YouTube.
     *
     * Accepte les formats :
     * - https://www.youtube.com/watch?v=UaH8cAGdjzw
     * - https://youtu.be/UaH8cAGdjzw
     *
     * Retourne l'URL standard ou false si le format n'est pas reconnu.
     */
    private function normalizeYouTubeUrl(string $url)
    {
        // Parse l'URL
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host'])) {
            return false;
        }

        // Gérer les différents formats d'URL YouTube
        if (strpos($parsedUrl['host'], 'youtu.be') !== false) {
            // Format raccourci : https://youtu.be/UaH8cAGdjzw
            if (isset($parsedUrl['path'])) {
                $videoId = ltrim($parsedUrl['path'], '/');
                return "https://www.youtube.com/watch?v={$videoId}";
            }
        } elseif (strpos($parsedUrl['host'], 'youtube.com') !== false) {
            // Format standard : https://www.youtube.com/watch?v=UaH8cAGdjzw
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                if (isset($queryParams['v'])) {
                    $videoId = $queryParams['v'];
                    return "https://www.youtube.com/watch?v={$videoId}";
                }
            }
        }

        // URL non reconnue
        return false;
    }
}
