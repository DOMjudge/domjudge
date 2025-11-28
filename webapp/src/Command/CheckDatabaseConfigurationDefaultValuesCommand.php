<?php declare(strict_types=1);

namespace App\Command;

use App\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'domjudge:db-config:check',
    description: 'Check if the default values of the database configuration are valid'
)]
class CheckDatabaseConfigurationDefaultValuesCommand
{
    public function __construct(
        protected readonly ConfigurationService $config,
    ) {}

    public function __invoke(SymfonyStyle $style): int
    {
        $messages = [];
        foreach ($this->config->getConfigSpecification() as $specification) {
            $message = sprintf(
                'Configuration %s (in category %s) is of type %s but has wrong type for default_value (%s)',
                $specification->name,
                $specification->category,
                $specification->type,
                json_encode($specification->defaultValue, JSON_THROW_ON_ERROR)
            );
            switch ($specification->type) {
                case 'bool':
                    if (!is_bool($specification->defaultValue)) {
                        $messages[] = $message;
                    }
                    break;
                case 'int':
                    if (!is_int($specification->defaultValue)) {
                        $messages[] = $message;
                    }
                    break;
                case 'string':
                    if (!is_string($specification->defaultValue)) {
                        $messages[] = $message;
                    }
                    break;
                case 'array_val':
                    if (!(empty($specification->defaultValue) || (
                            is_array($specification->defaultValue) &&
                            is_int(key($specification->defaultValue))))) {
                        $messages[] = $message;
                    }
                    break;
                case 'array_keyval':
                    if (!(empty($specification->defaultValue) || (
                            is_array($specification->defaultValue) &&
                            is_string(key($specification->defaultValue))))) {
                        $messages[] = $message;
                    }
                    break;
            }
        }
        if (!empty($messages)) {
            $style->error('Some default values have the wrong type:');
            $style->listing($messages);
            return Command::FAILURE;
        }
        $style->success('All default values have the correct type');
        return Command::SUCCESS;
    }
}
