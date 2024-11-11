import sys
import json
import yt_dlp
import os
import uuid
import requests
import logging
import subprocess
import io

# Assurez-vous que la sortie utilise UTF-8
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Configurez le logging
logging.basicConfig(level=logging.INFO)

def get_project_root():
    """
    Obtenir le chemin absolu du répertoire racine du projet Symfony.
    """
    script_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.abspath(os.path.join(script_dir, '../../../'))
    return project_root

def get_video_info(url, output_dir):
    """
    Récupérer les informations de la vidéo et télécharger la miniature.
    """
    try:
        ydl_opts = {
            'quiet': True,
            'no_warnings': True,
        }
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=False)
            title = info.get('title')
            thumbnail_url = info.get('thumbnail')

            if thumbnail_url:
                thumbnail_ext = os.path.splitext(thumbnail_url)[-1]
                thumbnail_filename = str(uuid.uuid4()) + thumbnail_ext
                thumbnail_path = os.path.join(output_dir, thumbnail_filename)
                os.makedirs(output_dir, exist_ok=True)

                response = requests.get(thumbnail_url)
                if response.status_code == 200:
                    with open(thumbnail_path, 'wb') as f:
                        f.write(response.content)
                    logging.info(f'Miniature téléchargée : {thumbnail_path}')
                else:
                    raise Exception(f"Erreur lors du téléchargement de la miniature : HTTP {response.status_code}")
            else:
                thumbnail_filename = None

            return {
                "title": title,
                "thumbnail": thumbnail_filename
            }

    except Exception as e:
        logging.error(f"Erreur lors de la récupération des informations : {e}")
        return {"error": str(e)}

def download_video(url, output_path, format):
    """
    Télécharger la vidéo avec audio intégré et renommer le fichier selon le titre et le format.
    """
    try:
        base_output = os.path.splitext(output_path)[0]
        video_output = base_output + "_video.mp4"
        audio_output = base_output + "_audio.mp3"

        # Options pour télécharger la vidéo sans audio
        video_opts = {
            'outtmpl': video_output,
            'format': 'bestvideo[ext=mp4]',
            'quiet': True,
            'no_warnings': True,
        }

        # Options pour télécharger l'audio uniquement
        audio_opts = {
            'outtmpl': audio_output,
            'format': 'bestaudio[ext=mp3]/bestaudio',
            'quiet': True,
            'no_warnings': True,
        }

        # Télécharger la vidéo sans audio
        with yt_dlp.YoutubeDL(video_opts) as ydl:
            ydl.download([url])
        logging.info(f'Vidéo téléchargée : {video_output}')

        # Télécharger l'audio
        with yt_dlp.YoutubeDL(audio_opts) as ydl:
            ydl.download([url])
        logging.info(f'Audio téléchargé : {audio_output}')

        # Choisir les codecs en fonction du format de sortie
        if format.lower() == 'webm':
            audio_codec = 'libopus'  # Utiliser Opus pour WebM
            video_codec = 'libvpx-vp9'  # Utiliser VP9 pour WebM
        else:
            audio_codec = 'aac'  # Utiliser AAC pour MP4 et autres formats compatibles
            video_codec = 'copy'  # Copier le codec vidéo sans ré-encodage

        # Fusionner la vidéo et l'audio en un seul fichier final
        command = [
            'ffmpeg', '-i', video_output, '-i', audio_output,
            '-c:v', video_codec, '-c:a', audio_codec,
            '-strict', 'experimental',
            output_path
        ]
        subprocess.run(command, check=True)
        logging.info(f'Vidéo finale créée : {output_path}')

        # Supprimer les fichiers temporaires
        os.remove(video_output)
        os.remove(audio_output)

    except subprocess.CalledProcessError as e:
        logging.error(f'Erreur lors de la fusion avec ffmpeg : {e}')
        raise
    except Exception as e:
        logging.error(f'Erreur lors du téléchargement ou de la conversion : {e}')
        raise

if __name__ == "__main__":
    try:
        if '--download' in sys.argv:
            if len(sys.argv) != 5:
                raise ValueError("Arguments invalides pour le téléchargement de la vidéo. Usage : python downloader.py --download <url> <output_path> <format>")

            url = sys.argv[2]
            output_path = sys.argv[3]
            format = sys.argv[4]
            logging.info(f'Téléchargement de la vidéo : {url} au format {format} vers {output_path}')
            download_video(url, output_path, format)

        elif '--info' in sys.argv:
            if len(sys.argv) != 4:
                raise ValueError("Arguments invalides pour la récupération des informations. Usage : python downloader.py --info <url> <output_dir>")

            url = sys.argv[2]
            output_dir = sys.argv[3]
            logging.info(f'Récupération des informations de la vidéo : {url}')
            video_info = get_video_info(url, output_dir)
            print(json.dumps(video_info, ensure_ascii=False, indent=4))  # Sortie JSON propre

    except Exception as e:
        logging.error(f"Erreur générale : {e}")
        print(json.dumps({"error": str(e)}, ensure_ascii=False), file=sys.stderr)
        sys.exit(1)
