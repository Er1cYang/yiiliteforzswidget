<?php
class CExpressionDependency extends CCacheDependency
{
public $expression;
public function __construct($expression='true')
{
$this->expression=$expression;
}
protected function generateDependentData()
{
return $this->evaluateExpression($this->expression);
}
}
