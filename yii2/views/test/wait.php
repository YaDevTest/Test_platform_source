<?php
$this->title = 'Обработка файла';
?>

<div class="test-wait text-center">
    <h1>Обработка файла</h1>

    <div class="card mx-auto mt-4" style="max-width: 600px;">
        <div class="card-body">
            <div id="status-icon" style="font-size: 48px;">⏳</div>
            <h3 id="status-text" class="mt-3">Ожидание...</h3>

            <div class="progress mt-3" style="height: 30px;">
                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar" style="width: 0%">0%</div>
            </div>

            <div id="logs" class="text-start mt-4" style="max-height: 300px; overflow-y: auto;">
            </div>
        </div>
    </div>
</div>

<script>
const taskId = '<?= $taskId ?>';
let polling;

function checkStatus() {
    fetch('/test/status?task_id=' + taskId)
        .then(r => r.json())
        .then(data => {
            const bar = document.getElementById('progress-bar');
            bar.style.width = data.progress + '%';
            bar.textContent = data.progress + '%';

            const logsDiv = document.getElementById('logs');
            logsDiv.innerHTML = (data.logs || []).map(l => '<div class="small text-muted">' + l + '</div>').join('');
            logsDiv.scrollTop = logsDiv.scrollHeight;

            if (data.status === 'completed') {
                clearInterval(polling);
                document.getElementById('status-icon').textContent = '✅';
                document.getElementById('status-text').textContent = 'Готово!';
                bar.classList.remove('progress-bar-animated');
                bar.classList.add('bg-success');

                const testId = data.statistics ? data.statistics.test_id : null;
                if (testId) {
                    setTimeout(() => {
                        window.location.href = '/test/configure?test_id=' + testId;
                    }, 1500);
                }
            } else if (data.status === 'failed') {
                clearInterval(polling);
                document.getElementById('status-icon').textContent = '❌';
                document.getElementById('status-text').textContent = 'Ошибка: ' + (data.error || 'Неизвестная');
                bar.classList.remove('progress-bar-animated');
                bar.classList.add('bg-danger');
            }
        })
        .catch(err => console.error('Poll error:', err));
}

polling = setInterval(checkStatus, 1500);
checkStatus();
</script>
