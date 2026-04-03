<?php

namespace app\models;

use yii\db\ActiveRecord;

class QuestionFormula extends ActiveRecord
{
    public static function tableName()
    {
        return 'question_formulas';
    }
}
