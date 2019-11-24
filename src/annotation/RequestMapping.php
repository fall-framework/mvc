<?php

namespace fall\mvc\annotation;

use fall\core\lang\Annotation;

/**
 * @author Angelis <angelis@users.noreply.github.com>
 */
interface RequestMapping extends Annotation
{
  public function value();
  public function method();
}
