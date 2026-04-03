<?php

namespace app\models;

use yii\db\ActiveRecord;

class Question extends ActiveRecord
{
    public static function tableName()
    {
        return 'questions';
    }

    public function getAnswers()
    {
        return $this->hasMany(Answer::class, ['question_id' => 'id'])
            ->orderBy(['position' => SORT_ASC]);
    }

    public function getMedia()
    {
        return $this->hasMany(QuestionMedia::class, ['question_id' => 'id']);
    }

    public function getFormulas()
    {
        return $this->hasMany(QuestionFormula::class, ['question_id' => 'id']);
    }

    /**
     * Возвращает текст вопроса с подставленными картинками и формулами
     */
    public function getRenderedText()
    {
        $text = $this->question_text;

        // Подставляем формулы (MathML)
        foreach ($this->formulas as $formula) {
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
        foreach ($this->media as $media) {
            if ($media->base64_data && $media->mime_type) {
                $img = '<img src="data:' . $media->mime_type . ';base64,' . $media->base64_data . '" class="question-image" alt="image">';
                $text = str_replace($media->marker, $img, $text);
            }
        }

        return $text;
    }
}
