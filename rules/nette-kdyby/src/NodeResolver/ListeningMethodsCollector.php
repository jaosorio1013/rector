<?php

declare(strict_types=1);

namespace Rector\NetteKdyby\NodeResolver;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Core\PhpParser\Node\Value\ValueResolver;
use Rector\Core\PhpParser\NodeTraverser\CallableNodeTraverser;
use Rector\NetteKdyby\Naming\EventClassNaming;
use Rector\NetteKdyby\ValueObject\NetteEventToContributeEventClass;

final class ListeningMethodsCollector
{
    /**
     * @var CallableNodeTraverser
     */
    private $callableNodeTraverser;

    /**
     * @var ValueResolver
     */
    private $valueResolver;

    /**
     * @var EventClassNaming
     */
    private $eventClassNaming;

    public function __construct(
        CallableNodeTraverser $callableNodeTraverser,
        ValueResolver $valueResolver,
        EventClassNaming $eventClassNaming
    ) {
        $this->callableNodeTraverser = $callableNodeTraverser;
        $this->valueResolver = $valueResolver;
        $this->eventClassNaming = $eventClassNaming;
    }

    /**
     * @return array<string, ClassMethod>
     */
    public function collectFromClassAndGetSubscribedEventClassMethod(Class_ $class, ClassMethod $classMethod): array
    {
        $classMethodsByEventClass = [];

        $this->callableNodeTraverser->traverseNodesWithCallable((array) $classMethod->stmts, function (Node $node) use (
            $class,
            &$classMethodsByEventClass
        ) {
            if (! $node instanceof ArrayItem) {
                return null;
            }

            $possibleMethodName = $this->valueResolver->getValue($node->value);
            if (! is_string($possibleMethodName)) {
                return null;
            }

            $classMethod = $class->getMethod($possibleMethodName);
            if ($classMethod === null) {
                return null;
            }

            if ($node->key === null) {
                return null;
            }

            $eventClass = $this->valueResolver->getValue($node->key);

            $contributeEventClasses = NetteEventToContributeEventClass::PROPERTY_TO_EVENT_CLASS;
            if (! in_array($eventClass, $contributeEventClasses, true)) {
                [$classMethod, $eventClass] = $this->resolveCustomClassMethodAndEventClass($node, $class, $eventClass);
            }

            if ($classMethod === null) {
                return null;
            }

            if (! is_string($eventClass)) {
                return null;
            }

            $classMethodsByEventClass[$eventClass] = $classMethod;
        });

        return $classMethodsByEventClass;
    }

    private function resolveCustomClassMethodAndEventClass(
        ArrayItem $arrayItem,
        Class_ $class,
        string $eventClass
    ): array {
        // custom method name
        $classMethodName = $this->valueResolver->getValue($arrayItem->value);
        $classMethod = $class->getMethod($classMethodName);

        if (Strings::contains($eventClass, '::')) {
            [$dispatchingClass, $property] = Strings::split($eventClass, '#::#');
            $eventClass = $this->eventClassNaming->createEventClassNameFromClassAndProperty(
                $dispatchingClass,
                $property
            );
        }

        return [$classMethod, $eventClass];
    }
}
