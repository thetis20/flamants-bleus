<?php

namespace App\Form;

use App\Model\Venue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class VenueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('slug', TextType::class, [
                'label' => 'Adresse de la page',
                'help' => 'Apparaît dans l\'URL : /lieux/blue-monkeys.',
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse postale',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('websiteUrl', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('onHome', CheckboxType::class, [
                'label' => 'Afficher sur la page d\'accueil',
                'required' => false,
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
        $resolver->setDefaults(['data_class' => Venue::class]);
    }
}
