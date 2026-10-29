<?php

declare(strict_types=1);

use App\Exceptions\Api\CanNotDeleteUserWhoIsOwnerOfOrganizationWithMultipleMembers;
use App\Exceptions\Api\CanNotRemoveOwnerFromOrganization;
use App\Exceptions\Api\ChangingRoleOfPlaceholderIsNotAllowed;
use App\Exceptions\Api\ChangingRoleToPlaceholderIsNotAllowed;
use App\Exceptions\Api\EntityStillInUseApiException;
use App\Exceptions\Api\FeatureIsNotAvailableInFreePlanApiException;
use App\Exceptions\Api\InactiveUserCanNotBeUsedApiException;
use App\Exceptions\Api\InvitationForTheEmailAlreadyExistsApiException;
use App\Exceptions\Api\OnlyOwnerCanChangeOwnership;
use App\Exceptions\Api\OnlyPlaceholdersCanBeMergedIntoAnotherMember;
use App\Exceptions\Api\OrganizationHasNoSubscriptionButMultipleMembersException;
use App\Exceptions\Api\OrganizationNeedsAtLeastOneOwner;
use App\Exceptions\Api\OverlappingTimeEntryApiException;
use App\Exceptions\Api\PdfRendererIsNotConfiguredException;
use App\Exceptions\Api\PersonalAccessClientIsNotConfiguredException;
use App\Exceptions\Api\ThisPlaceholderCanNotBeInvitedUseTheMergeToolInsteadException;
use App\Exceptions\Api\TimeEntryCanNotBeRestartedApiException;
use App\Exceptions\Api\TimeEntryStillRunningApiException;
use App\Exceptions\Api\UserIsAlreadyMemberOfOrganizationApiException;
use App\Exceptions\Api\UserIsAlreadyMemberOfProjectApiException;
use App\Exceptions\Api\UserNotPlaceholderApiException;
use App\Exceptions\Api\UserResendEmailVerificationNoPendingEmailApiException;
use App\Service\Export\ExportException;

return [
    'api' => [
        TimeEntryStillRunningApiException::KEY => 'El registro de tiempo sigue en curso',
        UserNotPlaceholderApiException::KEY => 'El usuario indicado no es un miembro provisional',
        TimeEntryCanNotBeRestartedApiException::KEY => 'El registro de tiempo ya se ha detenido y no se puede reanudar',
        InactiveUserCanNotBeUsedApiException::KEY => 'No se puede usar un usuario inactivo',
        UserIsAlreadyMemberOfOrganizationApiException::KEY => 'El usuario ya es miembro de la organización',
        UserIsAlreadyMemberOfProjectApiException::KEY => 'El usuario ya es miembro del proyecto',
        EntityStillInUseApiException::KEY => 'No se puede eliminar este elemento (:modelToDelete) porque todavía se usa en: :modelInUse.',
        CanNotRemoveOwnerFromOrganization::KEY => 'No se puede quitar al propietario de la organización',
        CanNotDeleteUserWhoIsOwnerOfOrganizationWithMultipleMembers::KEY => 'No se puede eliminar a un usuario que es propietario de una organización con varios miembros. Elimina primero la organización.',
        OnlyOwnerCanChangeOwnership::KEY => 'Solo el propietario puede transferir la propiedad',
        OrganizationNeedsAtLeastOneOwner::KEY => 'La organización necesita al menos un propietario',
        ChangingRoleToPlaceholderIsNotAllowed::KEY => 'No está permitido cambiar el rol a miembro provisional',
        ExportException::KEY => 'La exportación ha fallado. Inténtalo de nuevo más tarde o contacta con soporte',
        OrganizationHasNoSubscriptionButMultipleMembersException::KEY => 'La organización no tiene suscripción, pero tiene varios miembros',
        PdfRendererIsNotConfiguredException::KEY => 'El generador de PDF no está configurado',
        FeatureIsNotAvailableInFreePlanApiException::KEY => 'Esta función no está disponible en el plan gratuito',
        PersonalAccessClientIsNotConfiguredException::KEY => 'El cliente de acceso personal no está configurado',
        ChangingRoleOfPlaceholderIsNotAllowed::KEY => 'No está permitido cambiar el rol de un miembro provisional',
        OnlyPlaceholdersCanBeMergedIntoAnotherMember::KEY => 'Solo los miembros provisionales se pueden fusionar con otro miembro',
        ThisPlaceholderCanNotBeInvitedUseTheMergeToolInsteadException::KEY => 'Este miembro provisional no se puede invitar; usa la herramienta de fusión',
        InvitationForTheEmailAlreadyExistsApiException::KEY => 'Este correo ya ha sido invitado a la organización. Espera a que el usuario acepte la invitación o vuelve a enviar el correo de invitación.',
        OverlappingTimeEntryApiException::KEY => 'No se permiten registros de tiempo superpuestos.',
        UserResendEmailVerificationNoPendingEmailApiException::KEY => 'No se puede reenviar el correo: no hay ningún correo pendiente de verificación.',
    ],
    'unknown_error_in_admin_panel' => 'Se ha producido un error desconocido. Revisa los registros.',
];
