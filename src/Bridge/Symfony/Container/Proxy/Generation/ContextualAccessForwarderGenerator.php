<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use InvalidArgumentException;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;
use ProxyManager\Exception\InvalidProxiedClassException;
use ProxyManager\Generator\Util\ClassGeneratorUtils;
use ProxyManager\ProxyGenerator\Assertion\CanProxyAssertion;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\ProxiedMethodsFilter;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\GetWrappedServicePoolValue;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicGet;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicSet;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\StaticProxyConstructor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;

/**
 * Generator for proxies with service pool.
 */
final readonly class ContextualAccessForwarderGenerator implements ProxyGeneratorInterface
{
    public function __construct(private MethodForwarderBuilder $forwarderBuilder) {}

    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     * @throws InvalidArgumentException
     * @throws InvalidProxiedClassException
     */
    public function generate(ReflectionClass $originalClass, ClassGenerator $classGenerator): void
    {
        CanProxyAssertion::assertClassCanBeProxied($originalClass);

        $interfaces = [
            ContextualProxy::class,
        ];

        if ($originalClass->isInterface()) {
            $interfaces[] = $originalClass->getName();
        }

        if (!$originalClass->isInterface()) {
            $classGenerator->setExtendedClass($originalClass->getName());
        }

        $publicProperties = new PublicPropertiesMap(Properties::fromReflectionClass($originalClass));
        $classGenerator->setImplementedInterfaces($interfaces);
        $classGenerator->addPropertyFromGenerator($servicePoolProperty = new ServicePoolProperty());
        $classGenerator->addPropertyFromGenerator($publicProperties);
        $closure = static function (MethodGenerator $generatedMethod) use ($originalClass, $classGenerator): void {
            ClassGeneratorUtils::addMethodIfNotFinal($originalClass, $classGenerator, $generatedMethod);
        };

        array_map(
            $closure,
            array_merge(
                array_map(
                    $this->forwarderBuilder->buildMethodInterceptor($servicePoolProperty),
                    ProxiedMethodsFilter::getProxiedMethods($originalClass)
                ),
                [
                    new StaticProxyConstructor($servicePoolProperty, Properties::fromReflectionClass($originalClass)),
                    new GetWrappedServicePoolValue($servicePoolProperty),
                    new MagicGet($originalClass, $servicePoolProperty, $publicProperties),
                    new MagicSet($originalClass, $servicePoolProperty, $publicProperties),
                ]
            )
        );
    }
}
