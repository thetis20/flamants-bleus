<?php

namespace App\Form;

use App\Model\Format;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class FormatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Adresse de la page',
                'help' => 'Apparaît dans l\'URL : /formats/cabaret-impro.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG, WebP ou SVG, 1 Mo maximum.',
                'constraints' => [
                    new File(
                        maxSize: '1M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
                        mimeTypesMessage: 'Format d\'image non accepté.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Format::class]);
    }
}
