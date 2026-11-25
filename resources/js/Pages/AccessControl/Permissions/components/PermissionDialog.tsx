/**
 * PermissionDialog — create/rename the single editable field of a permission.
 *
 * Mounted with a key from the page so opening a different row resets the form
 * instead of copying old state through an effect. The dialog shell comes from
 * the form kit: it owns the `<form>`, the submit/cancel row, the error summary
 * and the dirty-close guard, so this file only declares the field and the call.
 */
import { FormDialog } from '@/Components/Forms';
import TextField from '@/Components/Forms/TextField';
import { useZodForm } from '@/lib/forms';
import { accessControlUrls } from '@/Modules/AccessControl/endpoints';
import { permissionSchema, type PermissionForm } from '@/Modules/AccessControl/schemas/permission';

interface PermissionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    permission?: Modules.AccessControl.Data.PermissionData;
    /** The filtered index to return to after a successful write. */
    returnTo?: string | null;
}

export default function PermissionDialog({
    open,
    onOpenChange,
    permission,
    returnTo = null,
}: PermissionDialogProps) {
    const initial: PermissionForm = {
        name: permission?.name ?? '',
        return_to: returnTo ?? '',
    };
    const { form, errors, submit, touch, clearError } = useZodForm({
        initial,
        schema: permissionSchema,
    });

    const onSubmit = (): void => {
        submit(() => {
            const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

            if (permission) {
                form.put(accessControlUrls.permission(permission.uuid), options);

                return;
            }

            form.post(accessControlUrls.permissions, options);
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={permission ? 'Rename permission' : 'New permission'}
            description={
                permission
                    ? 'Assignments are stored by id, so a rename keeps every role and user grant.'
                    : 'Custom permissions start unassigned. Add them to a role from the role screen.'
            }
            form={form}
            errors={errors}
            onSubmit={onSubmit}
            submitLabel={permission ? 'Save name' : 'Create permission'}
            submitDisabled={!form.isDirty}
            contentClassName="sm:max-w-md"
        >
            <TextField
                label="Permission name"
                name="name"
                value={form.data.name}
                onChange={(value) => {
                    form.setData('name', value.toUpperCase());
                    clearError('name');
                }}
                onBlur={() => touch('name')}
                error={errors.name}
                hint="Example: APPROVE_CLAIM. Uppercase letters, digits and underscores."
                required
                maxLength={64}
                autoCapitalize="characters"
                spellCheck={false}
                autoFocus
                disabled={form.processing}
            />
        </FormDialog>
    );
}
