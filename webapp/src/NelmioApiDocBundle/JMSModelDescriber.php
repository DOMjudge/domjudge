<?php declare(strict_types=1);

namespace App\NelmioApiDocBundle;

use App\DataTransferObject\Scoreboard\Problem;
use App\DataTransferObject\Scoreboard\Score;
use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Utils\CcsApiVersion;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\Model\ModelRegistry;
use Nelmio\ApiDocBundle\ModelDescriber\JMSModelDescriber as BaseJMSModelDescriber;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\TypeInfo\Type\ObjectType;

#[AsDecorator(decorates: 'nelmio_api_doc.model_describers.jms')]
class JMSModelDescriber implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        protected readonly BaseJMSModelDescriber $decorated,
        protected readonly ConfigurationService $config,
    ) {}

    public function describe(Model $model, Schema $schema): void
    {
        $this->decorated->describe($model, $schema);

        $type = $model->getTypeInfo();
        if (!$type instanceof ObjectType) {
            return;
        }

        /** @var CcsApiVersion $ccsApiVersion */
        $ccsApiVersion = $this->config->get('ccs_api_version');

        match ($type->getClassName()) {
            Contest::class => $this->setRelTimeProperty($schema, ['penalty_time'], $ccsApiVersion),
            Score::class   => $this->setRelTimeProperty($schema, ['total_time', 'time'], $ccsApiVersion),
            Problem::class => $this->setRelTimeProperty($schema, ['time'], $ccsApiVersion),
            default        => null,
        };
    }

    public function supports(Model $model): bool
    {
        return $this->decorated->supports($model);
    }

    public function setModelRegistry(ModelRegistry $modelRegistry): void
    {
        $this->decorated->setModelRegistry($modelRegistry);
    }

    /**
     * @param list<string> $propertiesToSet
     */
    protected function setRelTimeProperty(
        Schema $schema,
        array $propertiesToSet,
        CcsApiVersion $ccsApiVersion,
    ): void {
        foreach ($schema->properties as $property) {
            if (!in_array($property->property, $propertiesToSet, true)) {
                continue;
            }

            if ($ccsApiVersion->useRelTimes()) {
                $property->type = ' string';
            } else {
                $property->type = ' integer';
            }
            $property->nullable = true;
            $property->ref = Generator::UNDEFINED;
            /** @phpstan-ignore assign.propertyType */
            $property->oneOf = Generator::UNDEFINED;
        }
    }
}
