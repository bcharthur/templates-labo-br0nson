# templates/downloader/scripts/downloader.py

import sys
import json
import yt_dlp
import os
import uuid
import requests

def get_project_root():
    """
    Obtenir le chemin absolu du répertoire racine du projet Symfony.
    """
    script_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.abspath(os.path.join(script_dir, '../../../'))
    return project_root

def get_video_info(url):
    ydl_opts = {
        'quiet': True,
        'no_warnings': True,
    }
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(url, download=False)
        thumbnail_url = info.get('thumbnail')

        # Télécharger la miniature
        if thumbnail_url:
            # Générer un nom de fichier unique
            thumbnail_filename = str(uuid.uuid4()) + os.path.splitext(thumbnail_url)[-1]

            # Chemin absolu vers le répertoire public/images/cache
            project_root = get_project_root()
            thumbnail_dir = os.path.join(project_root, 'public', 'images', 'cache')
            thumbnail_path = os.path.join(thumbnail_dir, thumbnail_filename)

            # Créer le répertoire s'il n'existe pas
            os.makedirs(thumbnail_dir, exist_ok=True)

            # Télécharger l'image
            response = requests.get(thumbnail_url)
            if response.status_code == 200:
                with open(thumbnail_path, 'wb') as f:
                    f.write(response.content)
            else:
                raise Exception(f"Erreur lors du téléchargement de la miniature : HTTP {response.status_code}")

        else:
            thumbnail_filename = None

        return {
            "title": info.get('title'),
            "thumbnail": thumbnail_filename  # Retourner le nom du fichier
        }

def download_video(url, output_path):
    ydl_opts = {
        'outtmpl': output_path,
        'format': 'best',
        'quiet': True,
        'no_warnings': True,
    }
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        ydl.download([url])

if __name__ == "__main__":
    try:
        if '--download' in sys.argv:
            # Télécharger la vidéo
            if len(sys.argv) != 4:
                raise ValueError("Arguments invalides pour le téléchargement de la vidéo.")

            url = sys.argv[2]
            output_path = sys.argv[3]
            download_video(url, output_path)
        else:
            # Obtenir les informations de la vidéo
            if len(sys.argv) != 2:
                raise ValueError("Arguments invalides pour obtenir les informations de la vidéo.")

            url = sys.argv[1]
            video_info = get_video_info(url)
            print(json.dumps(video_info))
    except Exception as e:
        # Imprimer l'erreur sur la sortie d'erreur standard
        print(str(e), file=sys.stderr)
        sys.exit(1)
