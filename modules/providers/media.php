<?php
/**
 * /modules/providers/media.php - Módulo de Mídia (VERSÃO FINAL)
 *
 * RESPONSABILIDADES:
 * - Exibe campos de upload com preview de miniaturas interativas.
 * - Permite reordenar a galeria com drag-and-drop e remover fotos.
 * - Adiciona dicas visuais sobre o formato e tamanho ideal dos ficheiros.
 * - Suporta upload de vídeos.
 */
if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Contextos de tradução e dados existentes
$media_form_context = 'provider_media_form';
$common_messages_context = 'common_messages';

// Inicialização com fallback robusto
$existing_gallery_photos = [];
if (isset($form_data['existing_gallery_photos']) && is_array($form_data['existing_gallery_photos'])) {
    $existing_gallery_photos = $form_data['existing_gallery_photos'];
} elseif (isset($provider_data['gallery_photos']) && $provider_data['gallery_photos']) {
    $existing_gallery_photos = json_decode($provider_data['gallery_photos'], true) ?: [];
}

$existing_videos = [];
if (isset($form_data['existing_videos']) && is_array($form_data['existing_videos'])) {
    $existing_videos = $form_data['existing_videos'];
} elseif (isset($provider_data['videos']) && $provider_data['videos']) {
    $existing_videos = json_decode($provider_data['videos'], true) ?: [];
}

$current_main_photo_url = $form_data['main_photo_url'] ?? ($provider_data['main_photo_url'] ?? '');
$current_onlyfans_url = $form_data['onlyfans_url'] ?? ($provider_data['onlyfans_url'] ?? '');
$current_instagram_username = $form_data['instagram_username'] ?? ($provider_data['instagram_username'] ?? '');
$current_twitter_username = $form_data['twitter_username'] ?? ($provider_data['twitter_username'] ?? '');

// Traduções
$translations['module_title_media'] = getTranslation('module_title_media', $languageCode, $media_form_context);
$translations['media_module_description'] = getTranslation('media_module_description', $languageCode, $media_form_context);
$translations['label_main_photo'] = getTranslation('label_main_photo', $languageCode, $media_form_context);
$translations['label_gallery_photos'] = getTranslation('label_gallery_photos', $languageCode, $media_form_context);
$translations['label_videos'] = getTranslation('label_videos', $languageCode, $media_form_context, 'Vídeos');
$translations['label_social_media'] = getTranslation('label_social_media', $languageCode, $media_form_context);
$translations['ideal_size_hint_main'] = getTranslation('ideal_size_hint_main', $languageCode, $media_form_context, 'Tamanho ideal: 800x1200px, máx 10MB.');
$translations['ideal_size_hint_gallery'] = getTranslation('ideal_size_hint_gallery', $languageCode, $media_form_context, 'Adicione até 20 fotos. Arraste para reordenar. Máx 10MB por foto.');
$translations['ideal_size_hint_videos'] = getTranslation('ideal_size_hint_videos', $languageCode, $media_form_context, 'Adicione até 5 vídeos. Máx 50MB por vídeo.');
?>

<style>
    /* Estilos para a galeria de miniaturas interativa */
    .upload-hint { font-size: 0.8rem; color: #6c757d; margin-top: 5px; }
    .preview-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem; margin-top: 1rem; padding: 1rem; border: 2px dashed #ced4da; border-radius: 8px; min-height: 140px; align-content: flex-start; }
    .main-photo-preview-container { display: flex; justify-content: center; align-items: center; }
    .thumbnail-item { position: relative; width: 120px; height: 120px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: grab; }
    .thumbnail-item:active { cursor: grabbing; }
    .thumbnail-image, .thumbnail-video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .remove-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background-color: rgba(0, 0, 0, 0.6); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; justify-content: center; align-items: center; font-size: 16px; line-height: 1; font-weight: bold; transition: background-color 0.2s; }
    .remove-btn:hover { background-color: #dc3545; }
    .sortable-ghost { opacity: 0.4; border: 2px dashed #007bff; }
</style>

<fieldset class="form-module" id="media-module">
    <legend><?php echo htmlspecialchars($translations['module_title_media']); ?></legend>
    <p class="module-description"><?php echo htmlspecialchars($translations['media_module_description']); ?></p>

    <div class="form-grid">
        <div class="form-group full-width">
            <label for="main_photo"><?php echo htmlspecialchars($translations['label_main_photo']); ?></label>
            <input type="file" id="main_photo" name="main_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
            <p class="upload-hint"><?php echo htmlspecialchars($translations['ideal_size_hint_main']); ?></p>
            <div id="main-photo-container" class="preview-container main-photo-preview-container">
                <?php if ($current_main_photo_url): ?>
                    <div class="thumbnail-item" data-path="<?php echo htmlspecialchars($current_main_photo_url); ?>">
                        <img src="<?php echo htmlspecialchars(SITE_URL . $current_main_photo_url); ?>" class="thumbnail-image" alt="Foto Principal Atual">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group full-width">
            <label for="gallery_photos"><?php echo htmlspecialchars($translations['label_gallery_photos']); ?></label>
            <input type="file" id="gallery_photos" name="gallery_photos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
            <p class="upload-hint"><?php echo htmlspecialchars($translations['ideal_size_hint_gallery']); ?></p>
            <div id="gallery-container" class="preview-container">
                <?php foreach ($existing_gallery_photos as $photo_path): ?>
                    <div class="thumbnail-item" draggable="true" data-path="<?php echo htmlspecialchars($photo_path); ?>">
                        <img src="<?php echo htmlspecialchars(SITE_URL . $photo_path); ?>" class="thumbnail-image">
                        <button type="button" class="remove-btn">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="gallery_order" id="gallery_order_input">
            <div id="removed_photos_container"></div>
        </div>

        <div class="form-group full-width">
            <label for="videos"><?php echo htmlspecialchars($translations['label_videos']); ?></label>
            <input type="file" id="videos" name="videos[]" class="form-control" accept="video/mp4,video/avi" multiple>
            <p class="upload-hint"><?php echo htmlspecialchars($translations['ideal_size_hint_videos']); ?></p>
            <div id="videos-container" class="preview-container">
                <?php foreach ($existing_videos as $video_path): ?>
                    <div class="thumbnail-item" draggable="true" data-path="<?php echo htmlspecialchars($video_path); ?>">
                        <video class="thumbnail-video" controls>
                            <source src="<?php echo htmlspecialchars(SITE_URL . $video_path); ?>" type="video/mp4">
                        </video>
                        <button type="button" class="remove-btn">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="videos_order" id="videos_order_input">
            <div id="removed_videos_container"></div>
        </div>

        <div class="form-group full-width">
            <label class="group-title"><?php echo htmlspecialchars($translations['label_social_media']); ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                <input type="text" name="instagram_username" class="form-control" placeholder="Instagram" value="<?php echo htmlspecialchars($current_instagram_username); ?>">
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                <input type="text" name="twitter_username" class="form-control" placeholder="Twitter / X" value="<?php echo htmlspecialchars($current_twitter_username); ?>">
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-link"></i></span>
                <input type="url" name="onlyfans_url" class="form-control" placeholder="OnlyFans, Privacy, etc." value="<?php echo htmlspecialchars($current_onlyfans_url); ?>">
            </div>
        </div>
    </div>
</fieldset>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seletores de elementos
    const mainPhotoInput = document.getElementById('main_photo');
    const mainPhotoContainer = document.getElementById('main-photo-container');
    const galleryInput = document.getElementById('gallery_photos');
    const galleryContainer = document.getElementById('gallery-container');
    const galleryOrderInput = document.getElementById('gallery_order_input');
    const removedPhotosContainer = document.getElementById('removed_photos_container');
    const videosInput = document.getElementById('videos');
    const videosContainer = document.getElementById('videos-container');
    const videosOrderInput = document.getElementById('videos_order_input');
    const removedVideosContainer = document.getElementById('removed_videos_container');
    const form = document.querySelector('form');

    // Constantes de configuração
    const MAX_GALLERY_PHOTOS = 20;
    const MAX_VIDEOS = 5;
    const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    const MAX_VIDEO_SIZE = 50 * 1024 * 1024; // 50MB

    // Arrays para guardar novos ficheiros
    let newGalleryFiles = [];
    let newVideoFiles = [];

    // Função genérica para validar ficheiros
    function validateFile(file, maxSize, type) {
        if (file.size > maxSize) {
            alert(`O ficheiro ${file.name} excede o tamanho máximo de ${maxSize / 1024 / 1024}MB para ${type}.`);
            return false;
        }
        return true;
    }

    // Função genérica para criar previews
    function createPreview(container, file, type) {
        const div = document.createElement('div');
        div.className = 'thumbnail-item';
        div.setAttribute('draggable', 'true');
        div.fileObject = file; // Associa o objeto File ao elemento DOM
        // For new files, set a temporary data-path using the file name
        // This will be reconciled on the server-side with the actual path
        div.dataset.path = file.name; 

        const reader = new FileReader();
        reader.onload = (e) => {
            if (type === 'image') {
                div.innerHTML = `<img src="${e.target.result}" class="thumbnail-image"><button type="button" class="remove-btn">×</button>`;
            }
            container.appendChild(div);
        };

        if (type === 'image') {
            reader.readAsDataURL(file);
        } else if (type === 'video') {
            div.innerHTML = `<video class="thumbnail-video" controls><source src="${URL.createObjectURL(file)}" type="${file.type}"></video><button type="button" class="remove-btn">×</button>`;
            container.appendChild(div);
        }
    }

    // Processamento da Foto Principal
    mainPhotoInput.addEventListener('change', (event) => {
        mainPhotoContainer.innerHTML = '';
        const file = event.target.files[0];
        if (file && validateFile(file, MAX_IMAGE_SIZE, 'foto principal')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                mainPhotoContainer.innerHTML = `<div class="thumbnail-item"><img src="${e.target.result}" class="thumbnail-image" alt="Preview"></div>`;
            };
            reader.readAsDataURL(file);
        } else {
            mainPhotoInput.value = '';
        }
    });

    // Processamento da Galeria de Fotos
    galleryInput.addEventListener('change', (event) => {
        const files = Array.from(event.target.files);
        const currentTotal = galleryContainer.children.length + newGalleryFiles.length;
        if (currentTotal + files.length > MAX_GALLERY_PHOTOS) {
            alert(`Pode enviar um máximo de ${MAX_GALLERY_PHOTOS} fotos.`);
            return;
        }
        files.forEach(file => {
            if (validateFile(file, MAX_IMAGE_SIZE, 'fotos da galeria')) {
                newGalleryFiles.push(file);
                createPreview(galleryContainer, file, 'image');
            }
        });
        galleryInput.value = ''; // Limpa o input para permitir selecionar os mesmos ficheiros novamente
    });

    // Processamento de Vídeos
    videosInput.addEventListener('change', (event) => {
        const files = Array.from(event.target.files);
        const currentTotal = videosContainer.children.length + newVideoFiles.length;
        if (currentTotal + files.length > MAX_VIDEOS) {
            alert(`Pode enviar um máximo de ${MAX_VIDEOS} vídeos.`);
            return;
        }
        files.forEach(file => {
            if (validateFile(file, MAX_VIDEO_SIZE, 'vídeos')) {
                newVideoFiles.push(file);
                createPreview(videosContainer, file, 'video');
            }
        });
        videosInput.value = '';
    });
    
    // Função genérica para remover um item
    function handleRemove(event, container, filesArray, removedContainer, inputName) {
        if (event.target.classList.contains('remove-btn')) {
            const thumbnail = event.target.closest('.thumbnail-item');
            // Se for um item existente (tem data-path que começa com /uploads), marca para remoção no backend
            if (thumbnail.dataset.path && thumbnail.dataset.path.startsWith('/uploads/')) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `${inputName}[]`;
                hiddenInput.value = thumbnail.dataset.path;
                removedContainer.appendChild(hiddenInput);
            }
            // Se for um item novo (tem fileObject), remove do array de novos ficheiros
            if (thumbnail.fileObject) {
                const index = filesArray.indexOf(thumbnail.fileObject);
                if (index > -1) {
                    filesArray.splice(index, 1);
                }
            }
            thumbnail.remove();
        }
    }

    // Listeners para remoção
    galleryContainer.addEventListener('click', (e) => handleRemove(e, galleryContainer, newGalleryFiles, removedPhotosContainer, 'removed_gallery_photos'));
    videosContainer.addEventListener('click', (e) => handleRemove(e, videosContainer, newVideoFiles, removedVideosContainer, 'removed_videos'));

    // Drag-and-drop
    new Sortable(galleryContainer, { animation: 150, ghostClass: 'sortable-ghost' });
    new Sortable(videosContainer, { animation: 150, ghostClass: 'sortable-ghost' });

    // Preparar dados antes de submeter o formulário
    if (form) {
        form.addEventListener('submit', (event) => {
            // Ordem da galeria
            const galleryOrder = Array.from(galleryContainer.children).map(thumb => {
                // For existing files, use data-path (full URL); for new files, use file.name
                return thumb.dataset.path;
            }).filter(Boolean);
            galleryOrderInput.value = JSON.stringify(galleryOrder);

            // Ordem dos vídeos
            const videosOrder = Array.from(videosContainer.children).map(thumb => {
                // For existing files, use data-path (full URL); for new files, use file.name
                return thumb.dataset.path;
            }).filter(Boolean);
            videosOrderInput.value = JSON.stringify(videosOrder);

            // Anexar os ficheiros novos aos inputs para serem enviados
            // This is crucial: DataTransfer is used to create a FileList from our newGalleryFiles/newVideoFiles
            // and assign it back to the input's files property, so they get uploaded.
            const galleryDataTransfer = new DataTransfer();
            newGalleryFiles.forEach(file => galleryDataTransfer.items.add(file));
            galleryInput.files = galleryDataTransfer.files;

            const videosDataTransfer = new DataTransfer();
            newVideoFiles.forEach(file => videosDataTransfer.items.add(file));
            videosInput.files = videosDataTransfer.files;
        });
    }
});
</script>