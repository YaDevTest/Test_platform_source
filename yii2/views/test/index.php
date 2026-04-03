<?php
use yii\helpers\Html;

$this->title = 'Тесты';
?>

<div class="test-index">
    <h1>Платформа тестирования</h1>

    <!-- Загрузка файла -->
    <div class="card mb-4">
        <div class="card-header"><h3>Загрузить тест</h3></div>
        <div class="card-body">
            <?php if (Yii::$app->session->hasFlash('error')): ?>
                <div class="alert alert-danger"><?= Yii::$app->session->getFlash('error') ?></div>
            <?php endif; ?>

            <form action="<?= \yii\helpers\Url::to(['test/upload']) ?>" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Выберите DOCX файл с тестами:</label>
                    <input type="file" name="docx_file" accept=".docx" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    Загрузить и обработать
                </button>
            </form>
        </div>
    </div>

    <!-- Список тестов -->
    <?php if (!empty($tests)): ?>
    <div class="card">
        <div class="card-header"><h3>Доступные тесты</h3></div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Название</th>
                        <th>Вопросов</th>
                        <th>Дата</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?= $test->id ?></td>
                        <td><?= Html::encode($test->title) ?></td>
                        <td><span class="badge bg-info"><?= $test->getQuestionCount() ?></span></td>
                        <td><?= Yii::$app->formatter->asDatetime($test->created_at, 'short') ?></td>
                        <td>
                            <a href="<?= \yii\helpers\Url::to(['test/configure', 'test_id' => $test->id]) ?>"
                               class="btn btn-success btn-sm">
                                Начать тест
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Тестов пока нет. Загрузите DOCX файл.</div>
    <?php endif; ?>
</div>
