<?php

namespace voskobovich\nestedsets\actions;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use voskobovich\nestedsets\behaviors\NestedSetsBehavior;
use yii\db\ActiveRecord;
use yii\web\Response;

/**
 * Class MoveNodeAction
 * @package voskobovich\nestedsets\actions
 */
class MoveNodeAction extends Action
{
    /**
     * Class to use to locate the supplied data ids
     * @var string
     */
    public $modelClass;

    /**
     * Behavior key in list all behaviors on model
     * @var string
     */
    public $behaviorName = 'nestedSetsBehavior';

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (null == $this->modelClass) {
            throw new InvalidConfigException('Param "modelClass" must be contain model name with namespace.');
        }
    }

    /**
     * Move a node (model) below the parent and in between left and right
     *
     * @param integer $id the primaryKey of the moved node
     * @param integer $lft the primaryKey of the node left of the moved node
     * @param integer $rgt the primaryKey of the node right to the moved node
     * @param integer $par the primaryKey of the parent of the moved node
     * @return array
     * @throws InvalidConfigException
     */
    public function run($id, $lft, $rgt, $par)
    {
        /** @var ActiveRecord $model */
        $model = new $this->modelClass;
        /** @var NestedSetsBehavior $behavior */
        $behavior = $model->getBehavior($this->behaviorName);

        if ($behavior == null) {
            throw new InvalidConfigException('Behavior "' . $this->behaviorName . '" not found');
        }

        if (!$behavior instanceof NestedSetsBehavior) {
            throw new InvalidConfigException('Behavior must be implemented "voskobovich\nestedsets\behaviors\NestedSetsBehavior"');
        }

        /*
         * Locate the supplied model, left, right and parent models
         */
        $pkAttribute = $model->getTableSchema()->primaryKey[0];

        /** @var ActiveRecord|NestedSetsBehavior $currentModel */
        $currentModel = $model::find()->where([$pkAttribute => $id])->one();
        $lftModel = $model::find()->where([$pkAttribute => $lft])->one();
        $rgtModel = $model::find()->where([$pkAttribute => $rgt])->one();
        $parentModel = $model::find()->where([$pkAttribute => $par])->one();

        /*
         * Calculate the depth change
         */
        if (null == $parentModel) {
            $depthDelta = -1;
        } else if (null == ($parent = $currentModel->parents(1)->one())) {
            $depthDelta = 0;
        } else if ($parent->getPrimaryKey() != $parentModel->getPrimaryKey()) {
            $depthDelta = $parentModel->{$behavior->depthAttribute} - $currentModel->{$behavior->depthAttribute} + 1;
        } else {
            $depthDelta = 0;
        }

        /*
         * Calculate the left/right change
         */
        if (null == $lftModel) {
            $currentModel->moveNode((($parentModel ? $parentModel->{$behavior->leftAttribute} : 0) + 1), $depthDelta);
        } else if (null == $rgtModel) {
            $currentModel->moveNode((($lftModel ? $lftModel->{$behavior->rightAttribute} : 0) + 1), $depthDelta);
        } else {
            $currentModel->moveNode(($rgtModel ? $rgtModel->{$behavior->leftAttribute} : 0), $depthDelta);
        }

        /*
         * Response will be in JSON format
         */
        Yii::$app->response->format = Response::FORMAT_JSON;

        /*
         * Report new position
         */
        return [
            'id' => $currentModel->getPrimaryKey(),
            'depth' => $currentModel->{$behavior->depthAttribute},
            'lft' => $currentModel->{$behavior->leftAttribute},
            'rgt' => $currentModel->{$behavior->rightAttribute},
        ];
    }
}