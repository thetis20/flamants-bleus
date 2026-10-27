<?php

namespace App\Form;

use App\Model\TrackingLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TrackingLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'help' => 'Pour s\'y retrouver, par exemple « QR code flyer » ou « Bio Instagram ».',
            ]);

        if ($options['is_new']) {
            $builder->add('slug', TextType::class, [
                'label' => 'Code du lien',
                'help' => 'Le lien sera /r/<code>. Non modifiable ensuite (il peut être imprimé).',
            ]);
        }

        $builder->add('target', TextType::class, [
            'label' => 'Page d\'arrivée',
            'help' => '« / » pour l\'accueil, « /formats » pour les formats…',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrackingLink::class,
            'is_new' => true,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
