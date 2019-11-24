<?php

namespace fall\mvc\annotation;

use fall\context\stereotype\Controller;
use fall\core\lang\Annotation;

/**
 * @Controller()
 * @author Angelis <angelis@users.noreply.github.com>
 */
interface RestController extends Annotation
{
  /**
   * @DefaultValue("%class.short.name")
   */
  public function value();
}
