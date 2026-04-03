<?php
use yii\helpers\Html;

$this->title = 'Результаты';
$percent = $total > 0 ? round(($correct / $total) * 100) : 0;
$minutes = floor($timeSpent / 60);
$secs = $timeSpent % 60;

if ($percent >= 80) {
    $color = 'success';
    $emoji = '🎉';
    $message = 'Отлично!';
} elseif ($percent >= 60) {
    $color = 'warning';
    $emoji = '👍';
    $message = 'Хорошо!';
} else {
    $color = 'danger';
    $emoji = '📚';
    $message = 'Нужно подучить';
}
?>

<style>
.result-correct { border-left: 4px solid #198754; }
.result-wrong { border-left: 4px solid #dc3545; }
.result-skipped { border-left: 4px solid #6c757d; }
.correct-answer { background: #d1e7dd; border-radius: 6px; padding: 8px 12px; }
.wrong-answer { background: #f8d7da; border-radius: 6px; padding: 8px 12px; }
.question-image { max-width: 300px; max-height: 200px; vertical-align: middle; margin: 4px; }
.answer-image { max-width: 200px; max-height: 150px; vertical-align: middle; margin: 2px; }
.formula { display: inline-block; vertical-align: middle; margin: 0 4px; }
</style>

<div class="test-result">
    <!-- Общий результат -->
    <div class="card text-center mb-4">
        <div class="card-body py-5">
            <div style="font-size: 64px;"><?= $emoji ?></div>
            <h1 class="display-4 text-<?= $color ?>"><?= $correct ?> / <?= $total ?></h1>
            <h2><?= $message ?></h2>
            <div class="mt-3">
                <span class="badge bg-<?= $color ?> fs-4"><?= $percent ?>%</span>
                <span class="badge bg-secondary fs-4 ms-2">⏱ <?= $minutes ?>:<?= str_pad($secs, 2, '0', STR_PAD_LEFT) ?></span>
            </div>

            <div class="progress mt-4 mx-auto" style="height: 20px; max-width: 400px;">
                <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percent ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Разбор ответов -->
    <h3>Разбор ответов</h3>

    <?php foreach ($results as $idx => $result):
        $question = $result['question'];
        $cardClass = $result['user_answer'] === null ? 'result-skipped' : ($result['is_correct'] ? 'result-correct' : 'result-wrong');
    ?>
    <div class="card mb-3 <?= $cardClass ?>">
        <div class="card-body">
            <h5>
                <?php if ($result['is_correct']): ?>
                    <span class="badge bg-success">✓</span>
                <?php elseif ($result['user_answer'] === null): ?>
                    <span class="badge bg-secondary">—</span>
                <?php else: ?>
                    <span class="badge bg-danger">✗</span>
                <?php endif; ?>

                <span class="badge bg-dark me-2"><?= $idx + 1 ?></span>
                <?= $question->getRenderedText() ?>
            </h5>

            <div class="mt-3">
                <?php foreach ($question->answers as $answer): ?>
                    <?php
                    $isUserAnswer = ($answer->id == $result['user_answer']);
                    $isCorrectAnswer = ($answer->id == $result['correct_answer']);
                    $class = '';
                    if ($isCorrectAnswer) $class = 'correct-answer';
                    elseif ($isUserAnswer && !$result['is_correct']) $class = 'wrong-answer';
                    ?>
                    <div class="mb-2 <?= $class ?>">
                        <strong><?= Html::encode($answer->option_label) ?>)</strong>
                        <?= $answer->getRenderedText() ?>
                        <?php if ($isCorrectAnswer): ?> <span class="badge bg-success ms-1">правильный</span><?php endif; ?>
                        <?php if ($isUserAnswer && !$result['is_correct']): ?> <span class="badge bg-danger ms-1">ваш ответ</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="text-center mb-5">
        <a href="<?= \yii\helpers\Url::to(['test/configure', 'test_id' => $test->id]) ?>" class="btn btn-primary btn-lg">
            Пройти ещё раз
        </a>
        <a href="<?= \yii\helpers\Url::to(['test/index']) ?>" class="btn btn-outline-secondary btn-lg ms-2">
            К списку тестов
        </a>
    </div>
</div>
