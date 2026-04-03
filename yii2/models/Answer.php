<?php

namespace app\models;

use yii\db\ActiveRecord;

class Answer extends ActiveRecord
{
    public static function tableName()
    {
        return 'answers';
    }

    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    /**
     * Возвращает текст ответа с подставленными картинками и формулами
     */
    public function getRenderedText()
    {
        $text = $this->answer_text;
        $question = $this->question;

        // Подставляем формулы
        foreach ($question->formulas as $formula) {
            if ($formula->mathml) {
                $text = str_replace(
                    $formula->marker,
                    '<span class="formula">' . $formula->mathml . '</span>',
                    $text
                );
            } elseif ($formula->latex) {
                $text = str_replace(
                    $formula->marker,
                    '<code class="latex">' . htmlspecialchars($formula->latex) . '</code>',
                    $text
                );
            }
        }

        // Подставляем картинки
        foreach ($question->media as $media) {
            if ($media->base64_data && $media->mime_type) {
                $img = '<img src="data:' . $media->mime_type . ';base64,' . $media->base64_data . '" class="answer-image" alt="image">';
                $text = str_replace($media->marker, $img, $text);
            }
        }

        return $text;
    }
}
