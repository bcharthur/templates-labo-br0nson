// public/js/yt-yt-downloader/script-ytb.js

document.getElementById('search-btn').addEventListener('click', function () {
    const videoUrlInput = document.getElementById('video-url').value.trim();
    const searchBtn = document.getElementById('search-btn');
    const searchBtnText = document.getElementById('search-btn-text');
    const searchBtnSpinner = document.getElementById('search-btn-spinner');
    const videoDetails = document.getElementById('video-details');
    const thumbnail = document.getElementById('video-thumbnail');
    const thumbnailSpinner = document.getElementById('thumbnail-spinner');

    if (!videoUrlInput) {
        alert('Veuillez entrer une URL de vidéo.');
        return;
    }

    // Normaliser l'URL YouTube
    const normalizedUrl = normalizeYouTubeUrl(videoUrlInput);

    if (!normalizedUrl) {
        alert('URL YouTube invalide ou non supportée.');
        return;
    }

    // Afficher le spinner dans le bouton et désactiver le bouton
    searchBtnText.classList.add('d-none');
    searchBtnSpinner.classList.remove('d-none');
    searchBtn.disabled = true;

    // Masquer les détails de la vidéo
    videoDetails.classList.add('d-none');

    fetch('/yt-downloader/get-video-info', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: normalizedUrl }),
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur HTTP : ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // console.log('Réponse du serveur :', data);

            // Cacher le spinner dans le bouton et réactiver le bouton
            searchBtnText.classList.remove('d-none');
            searchBtnSpinner.classList.add('d-none');
            searchBtn.disabled = false;

            if (data.success) {
                // Afficher la card avec les détails de la vidéo
                videoDetails.classList.remove('d-none');

                // Afficher le spinner sur la miniature
                thumbnail.style.display = 'none';
                thumbnailSpinner.classList.remove('d-none');

                // Mettre à jour le titre de la vidéo
                document.getElementById('video-title').textContent = data.title;

                // Charger l'image de la miniature
                thumbnail.onload = function() {
                    // Cacher le spinner de la miniature une fois l'image chargée
                    thumbnailSpinner.classList.add('d-none');
                    thumbnail.style.display = 'block';
                };

                thumbnail.onerror = function() {
                    // Gérer l'erreur de chargement de l'image
                    thumbnailSpinner.classList.add('d-none');
                    alert('Erreur lors du chargement de la miniature.');
                };

                // Définir la source de l'image pour démarrer le chargement
                thumbnail.src = data.thumbnail;

                // Gérer le clic sur le bouton de téléchargement
                document.getElementById('download-btn').onclick = function () {
                    window.location.href = `/downloader/download-video?url=${encodeURIComponent(normalizedUrl)}`;
                };
            } else {
                alert(data.message + '\n' + (data.error || ''));
            }
        })
        .catch(error => {
            // Cacher le spinner dans le bouton et réactiver le bouton en cas d'erreur
            searchBtnText.classList.remove('d-none');
            searchBtnSpinner.classList.add('d-none');
            searchBtn.disabled = false;

            console.error('Erreur:', error);
            alert('Une erreur est survenue : ' + error.message);
        });
});

/**
 * Normaliser les URLs YouTube.
 *
 * Accepte les formats :
 * - https://www.youtube.com/watch?v=UaH8cAGdjzw
 * - https://youtu.be/UaH8cAGdjzw
 *
 * Retourne l'URL standard ou null si le format n'est pas reconnu.
 */
function normalizeYouTubeUrl(url) {
    try {
        const parsedUrl = new URL(url);

        if (parsedUrl.hostname === 'youtu.be') {
            // Format raccourci : https://youtu.be/UaH8cAGdjzw
            const videoId = parsedUrl.pathname.substring(1);
            if (videoId) {
                return `https://www.youtube.com/watch?v=${videoId}`;
            }
        } else if (parsedUrl.hostname === 'www.youtube.com' || parsedUrl.hostname === 'youtube.com') {
            // Format standard : https://www.youtube.com/watch?v=UaH8cAGdjzw
            const videoId = parsedUrl.searchParams.get('v');
            if (videoId) {
                return `https://www.youtube.com/watch?v=${videoId}`;
            }
        }

        // URL non reconnue
        return null;
    } catch (e) {
        // URL invalide
        return null;
    }
}
