<?php

namespace app\models;

use yii\db\ActiveRecord;

class QuestionMedia extends ActiveRecord
{
    public static function tableName()
    {
        return 'question_media';
    }
}
