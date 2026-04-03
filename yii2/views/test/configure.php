<?php
use yii\helpers\Html;

$this->title = 'Настройка теста';
?>

<div class="test-configure">
    <h1><?= Html::encode($test->title) ?></h1>
    <p class="text-muted">Всего вопросов в базе: <strong><?= $totalQuestions ?></strong></p>

    <div class="card mx-auto mt-4" style="max-width: 500px;">
        <div class="card-body text-center">
            <h3>Сколько вопросов?</h3>

            <form action="<?= \yii\helpers\Url::to(['test/start', 'test_id' => $test->id]) ?>" method="post">
                <div class="mt-4 mb-4">
                    <input type="range" class="form-range" id="questionRange"
                           name="question_count"
                           min="5" max="<?= $totalQuestions ?>"
                           value="<?= min(25, $totalQuestions) ?>"
                           oninput="document.getElementById('rangeValue').textContent = this.value">
                    <div style="font-size: 48px; font-weight: bold; color: #198754;" id="rangeValue">
                        <?= min(25, $totalQuestions) ?>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    <?php foreach ([10, 25, 50, $totalQuestions] as $preset): ?>
                        <?php if ($preset <= $totalQuestions): ?>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="document.getElementById('questionRange').value=<?= $preset ?>;document.getElementById('rangeValue').textContent=<?= $preset ?>">
                            <?= $preset === $totalQuestions ? 'Все (' . $totalQuestions . ')' : $preset ?>
                        </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100">
                    Начать тест
                </button>
            </form>
        </div>
    </div>

    <div class="text-center mt-3">
        <a href="<?= \yii\helpers\Url::to(['test/index']) ?>" class="text-muted">← Назад к списку</a>
    </div>
</div>
