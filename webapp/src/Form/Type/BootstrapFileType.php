<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\FileType;

class BootstrapFileType extends FileType
{
    public function getBlockPrefix()
    {
        return 'bootstrap_file';
    }
}
