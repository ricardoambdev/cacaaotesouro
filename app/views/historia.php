<?php

/**
 * Página História — texto da história do jogo (HTML).
 *
 * @var string $historyContent Conteúdo HTML da história
 */

$historyContent = $historyContent ?? '';
?>
<div class="page-header">
    <h1 class="page-title">História do Jogo</h1>
    <p class="page-subtitle">Este texto será exibido no aplicativo das equipes.</p>
</div>

<form method="post" action="/historia" class="settings-form" id="historyForm">
    <?= csrf_field() ?>

    <div class="settings-card">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/>
                <path d="M4 19l5-5"/>
                <path d="M20 19l-5-5"/>
                <line x1="9" y1="8" x2="15" y2="8"/>
            </svg>
            Conteúdo da história
        </h2>

        <div class="form-group">
            <label for="historyContent">Texto da história (HTML)</label>

            <!-- TinyMCE will enhance this textarea; fallback: plain textarea -->
            <textarea id="historyContent" name="historyContent" class="form-input" rows="16"
                      placeholder="A história do Quico e sua bola quadrada..."><?= e($historyContent) ?></textarea>
            <div class="error-inline"></div>
            <p class="form-help-text">O aplicativo exibe este texto na tela inicial do jogo.</p>
        </div>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn btn-primary" style="width: auto;">Salvar história</button>
    </div>
</form>

<?php if (!empty($historyContent)): ?>
    <div class="settings-card" style="margin-top: 24px;">
        <h2 class="settings-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            Pré-visualização
        </h2>
        <div class="history-preview">
            <?= $historyContent ?>
        </div>
    </div>
<?php endif; ?>

<!-- TinyMCE via jsDelivr (sem API key, skin completa) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
<script>
(function () {
    var textarea = document.getElementById('historyContent');
    var form = document.getElementById('historyForm');
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
        selector: '#historyContent',
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
