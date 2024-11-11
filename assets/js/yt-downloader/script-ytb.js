document.getElementById('search-btn').addEventListener('click', function () {
    const videoUrlInput = document.getElementById('video-url').value.trim();
    const searchBtn = document.getElementById('search-btn');
    const searchBtnText = document.getElementById('search-btn-text');
    const searchBtnSpinner = document.getElementById('search-btn-spinner');
    const videoDetails = document.getElementById('video-details');
    const thumbnail = document.getElementById('video-thumbnail');
    const thumbnailSpinner = document.getElementById('thumbnail-spinner');
    const formatSelect = document.getElementById('format-select');
    const downloadBtn = document.getElementById('download-btn');
    const downloadBtnText = document.getElementById('download-btn-text');
    const downloadBtnSpinner = document.getElementById('download-btn-spinner');

    if (!videoUrlInput) {
        alert('Veuillez entrer une URL de vidéo.');
        return;
    }

    // Afficher le spinner dans le bouton de recherche et désactiver le bouton
    searchBtnText.classList.add('d-none');
    searchBtnSpinner.classList.remove('d-none');
    searchBtn.disabled = true;

    // Masquer les détails de la vidéo
    videoDetails.classList.add('d-none');
    thumbnail.style.display = 'none';
    thumbnailSpinner.classList.remove('d-none');

    // Envoyer la requête pour obtenir les informations de la vidéo
    fetch('/yt-downloader/get-video-info', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: videoUrlInput })
    })
        .then(response => response.json())
        .then(data => {
            // Rétablir l'état initial du bouton de recherche
            searchBtnText.classList.remove('d-none');
            searchBtnSpinner.classList.add('d-none');
            searchBtn.disabled = false;

            if (data.success) {
                // Afficher les détails de la vidéo
                videoDetails.classList.remove('d-none');
                thumbnail.src = data.thumbnail;

                // Mettre à jour le titre avec le format choisi
                const selectedFormat = formatSelect.options[formatSelect.selectedIndex].text;
                document.getElementById('video-title').textContent = `${data.title} [${selectedFormat}]`;

                // Gérer le chargement de la miniature
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

                // Configurer l'événement de clic pour le bouton de téléchargement
                downloadBtn.onclick = function () {
                    const format = formatSelect.value;
                    const title = data.title;

                    const downloadData = {
                        url: videoUrlInput,
                        format: format,
                        title: title
                    };

                    // Afficher le spinner dans le bouton de téléchargement et désactiver le bouton
                    downloadBtnText.classList.add('d-none');
                    downloadBtnSpinner.classList.remove('d-none');
                    downloadBtn.disabled = true;

                    // Envoyer la requête de téléchargement
                    fetch('/yt-downloader/download-video', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(downloadData)
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Erreur lors du téléchargement de la vidéo.');
                            }
                            return response.blob();
                        })
                        .then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            a.download = `${title} [${format}].${format}`;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);

                            // Rétablir l'état initial du bouton de téléchargement
                            downloadBtnText.classList.remove('d-none');
                            downloadBtnSpinner.classList.add('d-none');
                            downloadBtn.disabled = false;
                        })
                        .catch(error => {
                            alert('Une erreur est survenue : ' + error.message);
                            downloadBtnText.classList.remove('d-none');
                            downloadBtnSpinner.classList.add('d-none');
                            downloadBtn.disabled = false;
                        });
                };
            } else {
                alert(data.message);
                // Masquer le spinner de la miniature si une erreur survient
                thumbnailSpinner.classList.add('d-none');
            }
        })
        .catch(error => {
            // Rétablir l'état initial du bouton de recherche en cas d'erreur
            searchBtnText.classList.remove('d-none');
            searchBtnSpinner.classList.add('d-none');
            searchBtn.disabled = false;
            thumbnailSpinner.classList.add('d-none');
            console.error('Erreur:', error);
            alert('Une erreur est survenue : ' + error.message);
        });
});
