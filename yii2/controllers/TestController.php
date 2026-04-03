<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;
use app\models\Test;
use app\models\Question;

class TestController extends Controller
{
    public $enableCsrfValidation = false;

    private function redis(): \Redis
    {
        $r = new \Redis();
        $r->connect(getenv('REDIS_HOST') ?: 'redis', 6379);
        $r->auth(getenv('REDIS_PASSWORD') ?: '');
        return $r;
    }

    private function cacheSet(string $key, $data, int $ttl = 7200): void
    {
        try {
            $r = $this->redis();
            $r->setex($key, $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            Yii::warning("Redis SET error: " . $e->getMessage(), 'cache');
        }
    }

    private function cacheGet(string $key)
    {
        try {
            $r    = $this->redis();
            $data = $r->get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            Yii::warning("Redis GET error: " . $e->getMessage(), 'cache');
            return null;
        }
    }

    private function cacheDel(string $key): void
    {
        try {
            $this->redis()->del($key);
        } catch (\Exception $e) {}
    }

    public function actionIndex()
    {
        $tests = Test::find()->orderBy(['created_at' => SORT_DESC])->all();
        return $this->render('index', ['tests' => $tests]);
    }

    public function actionUpload()
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        $file = UploadedFile::getInstanceByName('docx_file');
        if (!$file) {
            Yii::$app->session->setFlash('error', 'Файл не выбран');
            return $this->redirect(['index']);
        }
        $ch = curl_init();
        $cfile = new \CURLFile($file->tempName, $file->type, $file->name);
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'http://fastapi:8000/api/upload',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 202) {
            $data   = json_decode($response, true);
            $taskId = $data['task_id'] ?? null;
            if ($taskId) {
                return $this->redirect(['wait', 'task_id' => $taskId]);
            }
        }
        Yii::$app->session->setFlash('error', 'Ошибка загрузки: ' . $response);
        return $this->redirect(['index']);
    }

    public function actionWait($task_id)
    {
        return $this->render('wait', ['taskId' => $task_id]);
    }

    public function actionStatus($task_id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "http://fastapi:8000/api/status/{$task_id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function actionConfigure($test_id)
    {
        $test = Test::findOne($test_id);
        if (!$test) {
            throw new \yii\web\NotFoundHttpException('Тест не найден');
        }
        return $this->render('configure', [
            'test'           => $test,
            'totalQuestions' => $test->getQuestionCount(),
        ]);
    }

    public function actionStart($test_id)
    {
        $count = (int) Yii::$app->request->post('question_count', 10);
        $test  = Test::findOne($test_id);
        if (!$test) {
            throw new \yii\web\NotFoundHttpException('Тест не найден');
        }

        $questions   = Question::find()
            ->where(['test_id' => $test_id])
            ->orderBy('RAND()')
            ->limit($count)
            ->with(['answers'])
            ->all();
        $questionIds = array_map(fn($q) => $q->id, $questions);
        $cacheKey    = 'q_' . md5(implode(',', $questionIds));

        // Кэшируем в Redis
        $cacheData = [];
        foreach ($questions as $q) {
            $answers = [];
            foreach ($q->answers as $a) {
                $answers[] = [
                    'id'         => $a->id,
                    'text'       => $a->answer_text,
                    'label'      => $a->option_label,
                    'is_correct' => (bool) $a->is_correct,
                ];
            }
            $cacheData[$q->id] = [
                'id'      => $q->id,
                'text'    => $q->question_text,
                'answers' => $answers,
            ];
        }
        $this->cacheSet($cacheKey, $cacheData);

        Yii::$app->session->set('test_question_ids', $questionIds);
        Yii::$app->session->set('test_cache_key', $cacheKey);
        Yii::$app->session->set('test_id', $test_id);
        Yii::$app->session->set('test_started', time());

        return $this->redirect(['run']);
    }

    public function actionRun()
    {
        $questionIds = Yii::$app->session->get('test_question_ids');
        $testId      = Yii::$app->session->get('test_id');
        $cacheKey    = Yii::$app->session->get('test_cache_key');

        if (!$questionIds || !$testId) {
            return $this->redirect(['index']);
        }

        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            Yii::info("Redis HIT: {$cacheKey}", 'cache');
        } else {
            Yii::info("Redis MISS: {$cacheKey}", 'cache');
        }

        // Всегда грузим объекты для вью (медиа, формулы)
        $questions = Question::find()
            ->where(['id' => $questionIds])
            ->with(['answers', 'media', 'formulas'])
            ->indexBy('id')
            ->all();

        $ordered = [];
        foreach ($questionIds as $id) {
            if (isset($questions[$id])) {
                $ordered[] = $questions[$id];
            }
        }

        return $this->render('run', [
            'test'      => Test::findOne($testId),
            'questions' => $ordered,
        ]);
    }

    public function actionSubmit()
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        $questionIds = Yii::$app->session->get('test_question_ids');
        $testId      = Yii::$app->session->get('test_id');
        $startTime   = Yii::$app->session->get('test_started');
        $cacheKey    = Yii::$app->session->get('test_cache_key');

        if (!$questionIds || !$testId) {
            return $this->redirect(['index']);
        }

        $userAnswers = Yii::$app->request->post('answers', []);
        $questions   = Question::find()
            ->where(['id' => $questionIds])
            ->with(['answers'])
            ->indexBy('id')
            ->all();

        $correct = 0;
        $total   = count($questionIds);
        $results = [];

        foreach ($questionIds as $qId) {
            $question = $questions[$qId] ?? null;
            if (!$question) continue;

            $userAnswer    = isset($userAnswers[$qId]) ? (int) $userAnswers[$qId] : null;
            $correctAnswer = null;
            foreach ($question->answers as $a) {
                if ($a->is_correct) { $correctAnswer = $a->id; break; }
            }

            $isCorrect = ($userAnswer === $correctAnswer);
            if ($isCorrect) $correct++;

            $results[] = [
                'question'       => $question,
                'user_answer'    => $userAnswer,
                'correct_answer' => $correctAnswer,
                'is_correct'     => $isCorrect,
            ];
        }

        $this->cacheDel($cacheKey);
        Yii::$app->session->remove('test_question_ids');
        Yii::$app->session->remove('test_id');
        Yii::$app->session->remove('test_started');
        Yii::$app->session->remove('test_cache_key');

        return $this->render('result', [
            'results'   => $results,
            'correct'   => $correct,
            'total'     => $total,
            'timeSpent' => $startTime ? (time() - $startTime) : 0,
            'test'      => Test::findOne($testId),
        ]);
    }
}
