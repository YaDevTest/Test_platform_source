<?php
use yii\helpers\Html;

$this->title = 'Тестирование: ' . $test->title;
?>

<style>
.question-card { border-left: 4px solid #198754; margin-bottom: 20px; }
.question-card.answered { border-left-color: #0d6efd; }
.question-image { max-width: 300px; max-height: 200px; vertical-align: middle; margin: 4px; }
.answer-image { max-width: 200px; max-height: 150px; vertical-align: middle; margin: 2px; }
.formula { display: inline-block; vertical-align: middle; margin: 0 4px; }
.answer-option { padding: 10px 15px; border: 2px solid #dee2e6; border-radius: 8px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; }
.answer-option:hover { border-color: #0d6efd; background: #f0f7ff; }
.answer-option.selected { border-color: #0d6efd; background: #e7f1ff; }
#timer { position: fixed; top: 10px; right: 20px; background: #343a40; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: 20px; z-index: 1000; }
#progress-info { position: fixed; top: 10px; left: 20px; background: #198754; color: #fff; padding: 10px 20px; border-radius: 8px; z-index: 1000; }
</style>

<div id="timer">⏱ <span id="time">00:00</span></div>
<div id="progress-info">Отвечено: <span id="answered-count">0</span> / <?= count($questions) ?></div>

<div class="test-run mt-5">
    <h2 class="mb-4"><?= Html::encode($test->title) ?></h2>

    <form id="test-form" action="<?= \yii\helpers\Url::to(['test/submit']) ?>" method="post">

        <?php foreach ($questions as $idx => $question): ?>
        <div class="card question-card" id="q-<?= $question->id ?>">
            <div class="card-body">
                <h5 class="card-title">
                    <span class="badge bg-secondary"><?= $idx + 1 ?></span>
                    <?= $question->getRenderedText() ?>
                </h5>

                <div class="answers mt-3">
                    <?php foreach ($question->answers as $answer): ?>
                    <label class="answer-option d-block" id="opt-<?= $answer->id ?>">
                        <input type="radio"
                               name="answers[<?= $question->id ?>]"
                               value="<?= $answer->id ?>"
                               style="display: none;"
                               onchange="selectAnswer(this, <?= $question->id ?>)">
                        <span class="badge bg-outline-secondary me-2"><?= Html::encode($answer->option_label) ?></span>
                        <?= $answer->getRenderedText() ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="text-center mb-5">
            <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                Завершить тест
            </button>
        </div>
    </form>
</div>

<script>
// Таймер
let seconds = 0;
setInterval(() => {
    seconds++;
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    document.getElementById('time').textContent = m + ':' + s;
}, 1000);

// Выбор ответа
function selectAnswer(input, questionId) {
    // Убираем selected у всех в этом вопросе
    document.querySelectorAll('#q-' + questionId + ' .answer-option').forEach(el => {
        el.classList.remove('selected');
    });
    // Добавляем selected
    input.closest('.answer-option').classList.add('selected');
    document.getElementById('q-' + questionId).classList.add('answered');

    // Обновляем счётчик
    const answered = document.querySelectorAll('.question-card.answered').length;
    document.getElementById('answered-count').textContent = answered;
}

// Подтверждение перед отправкой
document.getElementById('test-form').addEventListener('submit', function(e) {
    const total = <?= count($questions) ?>;
    const answered = document.querySelectorAll('.question-card.answered').length;
    if (answered < total) {
        if (!confirm('Вы ответили на ' + answered + ' из ' + total + ' вопросов. Завершить?')) {
            e.preventDefault();
        }
    }
});
</script>
