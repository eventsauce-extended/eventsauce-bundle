<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\Acl;
use Andreo\EventSauceBundle\Attribute\AclInboundTarget;
use Andreo\EventSauceBundle\Attribute\AclMessageFilterChain;
use Andreo\EventSauceBundle\Attribute\AclOutboundTarget;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterAfter;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterBefore;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;
use ReflectionClass;
use Reflector;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AclLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $aclConfig = $config['acl'];
        $aclEnabled = $this->extension->isConfigEnabled($this->container, $aclConfig);
        if (!$aclEnabled) {
            return;
        }
        $outboundConfig = $aclConfig['outbound'];
        $inboundConfig = $aclConfig['inbound'];
        $outboundEnabled = $this->extension->isConfigEnabled($this->container, $outboundConfig);
        $inboundEnabled = $this->extension->isConfigEnabled($this->container, $inboundConfig);

        if (!$outboundEnabled && !$inboundEnabled) {
            return;
        }

        $this->container->registerAttributeForAutoconfiguration(
            Acl::class,
            static function (ChildDefinition $definition, Acl $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled, $outboundConfig, $inboundConfig): void {
                assert($reflector instanceof ReflectionClass);
                if ($outboundEnabled && $reflector->implementsInterface(MessageDispatcher::class)) {
                    $definition->addTag('andreo.eventsauce.acl_outbound');

                    $filterStrategyAttrRef = $reflector->getAttributes(AclMessageFilterChain::class)[0] ?? null;
                    if (null === $filterStrategyAttrRef) {
                        $filterChainConfig = $outboundConfig['filter_chain'];
                        $definition->addTag('andreo.eventsauce.acl.filter_chain', [
                            'before' => $filterChainConfig['before'],
                            'after' => $filterChainConfig['after'],
                        ]);
                    } else {
                        /** @var AclMessageFilterChain $filterStrategyAttr */
                        $filterStrategyAttr = $filterStrategyAttrRef->newInstance();
                        $definition->addTag('andreo.eventsauce.acl.filter_chain', [
                            'before' => $filterStrategyAttr->beforeTranslate,
                            'after' => $filterStrategyAttr->afterTranslate,
                        ]);
                    }
                } elseif ($inboundEnabled && $reflector->implementsInterface(MessageConsumer::class)) {
                    $definition->addTag('andreo.eventsauce.acl_inbound');

                    $filterStrategyAttrRef = $reflector->getAttributes(AclMessageFilterChain::class)[0] ?? null;
                    if (null === $filterStrategyAttrRef) {
                        $filterChainConfig = $inboundConfig['filter_chain'];
                        $definition->addTag('andreo.eventsauce.acl.filter_chain', [
                            'before' => $filterChainConfig['before'],
                            'after' => $filterChainConfig['after'],
                        ]);
                    } else {
                        /** @var AclMessageFilterChain $filterStrategyAttr */
                        $filterStrategyAttr = $filterStrategyAttrRef->newInstance();
                        $definition->addTag('andreo.eventsauce.acl.filter_chain', [
                            'before' => $filterStrategyAttr->beforeTranslate,
                            'after' => $filterStrategyAttr->afterTranslate,
                        ]);
                    }
                }
            }
        );

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageFilterBefore::class,
            static function (ChildDefinition $definition, AsMessageFilterBefore $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $aclInboundTargetReflections = $reflector->getAttributes(AclInboundTarget::class);
                $aclOutboundTargetReflections = $reflector->getAttributes(AclOutboundTarget::class);

                if (empty($aclInboundTargetReflections) && empty($aclOutboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($aclOutboundTargetReflections as $outboundTargetReflection) {
                        /** @var AclOutboundTarget $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $outboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_outbound_target', [
                                'id' => $outboundTargetAttr->id,
                            ]);
                        }
                    }
                }

                if ($inboundEnabled) {
                    foreach ($aclInboundTargetReflections as $inboundTargetReflection) {
                        /** @var AclInboundTarget $inboundTargetAttr */
                        $inboundTargetAttr = $inboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $inboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_inbound_target', [
                                'id' => $inboundTargetAttr->id,
                            ]);
                        }
                    }
                }
            }
        );

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageFilterAfter::class,
            static function (ChildDefinition $definition, AsMessageFilterAfter $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $aclInboundTargetReflections = $reflector->getAttributes(AclInboundTarget::class);
                $aclOutboundTargetReflections = $reflector->getAttributes(AclOutboundTarget::class);

                if (empty($aclInboundTargetReflections) && empty($aclOutboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($aclOutboundTargetReflections as $outboundTargetReflection) {
                        /** @var AclOutboundTarget $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $outboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_outbound_target', [
                                'id' => $outboundTargetAttr->id,
                            ]);
                        }
                    }
                }

                if ($inboundEnabled) {
                    foreach ($aclInboundTargetReflections as $inboundTargetReflection) {
                        /** @var AclInboundTarget $inboundTargetAttr */
                        $inboundTargetAttr = $inboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $inboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_inbound_target', [
                                'id' => $inboundTargetAttr->id,
                            ]);
                        }
                    }
                }
            }
        );

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageTranslator::class,
            static function (ChildDefinition $definition, AsMessageTranslator $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $aclInboundTargetReflections = $reflector->getAttributes(AclInboundTarget::class);
                $aclOutboundTargetReflections = $reflector->getAttributes(AclOutboundTarget::class);

                if (empty($aclInboundTargetReflections) && empty($aclOutboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($aclOutboundTargetReflections as $outboundTargetReflection) {
                        /** @var AclOutboundTarget $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $outboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_outbound_target', [
                                'id' => $outboundTargetAttr->id,
                            ]);
                        }
                    }
                }

                if ($inboundEnabled) {
                    foreach ($aclInboundTargetReflections as $inboundTargetReflection) {
                        /** @var AclInboundTarget $inboundTargetAttr */
                        $inboundTargetAttr = $inboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_inbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $inboundTargetAttr->id) {
                            $definition->addTag('andreo.eventsauce.acl_inbound_target', [
                                'id' => $inboundTargetAttr->id,
                            ]);
                        }
                    }
                }
            }
        );
    }
}
