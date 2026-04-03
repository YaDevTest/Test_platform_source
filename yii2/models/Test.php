<?php

namespace app\models;

use yii\db\ActiveRecord;

class Test extends ActiveRecord
{
    public static function tableName()
    {
        return 'tests';
    }

    public function getQuestions()
    {
        return $this->hasMany(Question::class, ['test_id' => 'id'])
            ->orderBy(['position' => SORT_ASC]);
    }

    public function getQuestionCount()
    {
        return $this->hasMany(Question::class, ['test_id' => 'id'])->count();
    }
}
