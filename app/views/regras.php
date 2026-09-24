<?php

/**
 * Página Regras — texto das regras da gincana (HTML).
 *
 * @var string $rulesContent Conteúdo HTML das regras
 */

$rulesContent = $rulesContent ?? '';
?>
<div class="page-header">
    <h1 class="page-title">Regras do Jogo</h1>
    <p class="page-subtitle">Escreva aqui as regras — elas aparecem no app das equipes e no app do admin.</p>
</div>

<form method="post" action="/regras" class="settings-form" id="rulesForm">
    <?= csrf_field() ?>

    <div class="settings-card">
        <h2 class="settings-card-title">
            <?= icon('menu_book', 20) ?>
            Conteúdo das regras
        </h2>

        <div class="form-group">
            <label for="rulesContent">Texto das regras (HTML)</label>

            <!-- TinyMCE will enhance this textarea; fallback: plain textarea -->
            <textarea id="rulesContent" name="rulesContent" class="form-input" rows="16"
                      placeholder="Regras da gincana..."><?= e($rulesContent) ?></textarea>
            <div class="error-inline"></div>
            <p class="form-help-text">O aplicativo exibe este texto na aba 'Regras'.</p>
        </div>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar regras</button>
    </div>
</form>

<?php if (!empty($rulesContent)): ?>
    <div class="settings-card" style="margin-top: 24px;">
        <h2 class="settings-card-title">
            <?= icon('visibility', 20) ?>
            Pré-visualização
        </h2>
        <div class="history-preview">
            <?= $rulesContent ?>
        </div>
    </div>
<?php endif; ?>

<!-- TinyMCE via jsDelivr (sem API key, skin completa) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
<script>
(function () {
    var textarea = document.getElementById('rulesContent');
    var form = document.getElementById('rulesForm');
    if (!textarea || !form) return;

    var started = false;

    function keepTextarea() {
        // Garante que o textarea fique visível/utilizável (fallback sólido)
        textarea.style.display = '';
    }

    // Se o TinyMCE não carregou, mantém o textarea simples
    if (typeof tinymce === 'undefined') {
        keepTextarea();
        return;
    }

    // Timeout de segurança: se o editor não iniciar em 8s, mantém o textarea
    var safetyTimer = setTimeout(function () {
        if (!started) keepTextarea();
    }, 8000);

    tinymce.init({
        selector: '#rulesContent',
        height: 420,
        menubar: false,
        branding: false,
        promotion: false,
        statusbar: false,
        plugins: 'advlist autolink lists link image charmap anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
        toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        content_style: 'body { font-family: Inter, Arial, sans-serif; font-size: 15px; color: #f7ecd4; background: #050B12; }',
        setup: function (editor) {
            editor.on('change', function () { editor.save(); });
        },
        init_instance_callback: function (editor) {
            started = true;
            clearTimeout(safetyTimer);
            // Só esconde o textarea DEPOIS que o editor funcionou
            textarea.style.display = 'none';
            // Sincroniza no submit
            form.addEventListener('submit', function () { editor.save(); });
        }
    });
})();
</script>
