<?php

namespace App\Controller\Admin;

use App\Entity\EmailTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EmailTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EmailTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Template email')
            ->setEntityLabelInPlural('Templates email')
            ->setDefaultSort(['project' => 'ASC', 'slug' => 'ASC'])
            ->setSearchFields(['slug', 'project', 'subject']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('slug')
            ->setHelp('Identifiant unique (ex. dartsopen_inscription_confirmation). Non modifiable après création.');
        yield TextField::new('project')
            ->setHelp('Étiquette de l\'application (ex. dartsopen, festmanager, global).')
            ->setRequired(false);
        yield TextField::new('subject')
            ->setHelp('Objet de l\'email. Peut contenir des variables Twig : {{ nom }}.');
        yield TextareaField::new('htmlBody', 'Corps HTML')
            ->setNumOfRows(20)
            ->setHelp('Template Twig complet. Variables disponibles définies dans le champ Description.')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Variables disponibles')
            ->setNumOfRows(5)
            ->setHelp('Documentation des variables attendues (ex. {{ nom }}, {{ tournoi }}).')
            ->setRequired(false)
            ->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }
}
