import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Link2, LogOut, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api } from '@/lib/api';
import type { Auth } from '@/types/auth';

type ShortUrlItem = {
    id: number;
    code: string;
    original_url: string;
    short_url: string;
    clicks: number;
    created_at: string | null;
};

type Props = {
    urls: ShortUrlItem[];
    highlightId?: number | null;
    flashSuccess?: string | null;
};

type PageProps = {
    auth: Auth;
    flash: {
        success?: string | null;
    };
};

export default function UrlsIndex({ urls, highlightId, flashSuccess }: Props) {
    const { auth, flash } = usePage<PageProps>().props;
    const createForm = useForm({ original_url: '' });
    const [editing, setEditing] = useState<ShortUrlItem | null>(null);
    const editForm = useForm({ original_url: '' });

    useEffect(() => {
        const message = flashSuccess || flash.success;

        if (message) {
            toast.success(message);
        }
    }, [flashSuccess, flash.success]);

    function createUrl(event: FormEvent) {
        event.preventDefault();
        createForm.post('/urls', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    function openEdit(url: ShortUrlItem) {
        setEditing(url);
        editForm.setData('original_url', url.original_url);
        editForm.clearErrors();
    }

    function saveEdit(event: FormEvent) {
        event.preventDefault();

        if (!editing) {
            return;
        }

        editForm.put(`/urls/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function removeUrl(url: ShortUrlItem) {
        if (!window.confirm(`¿Eliminar /${url.code}?`)) {
            return;
        }

        router.delete(`/urls/${url.id}`, { preserveScroll: true });
    }

    async function logout() {
        try {
            await api('/api/auth/logout', { method: 'POST' });
        } finally {
            router.visit('/');
        }
    }

    return (
        <>
            <Head title="Mis URLs" />
            <div className="min-h-screen bg-[#f3f7f6] text-[#102226]">
                <header className="border-b border-teal-900/10 bg-[#071a1f] text-[#e8f4f2]">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                        <a href="/" className="flex items-center gap-2">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-teal-400/15 text-teal-300 ring-1 ring-teal-300/30">
                                <Link2 className="size-4" />
                            </span>
                            <span className="text-lg font-semibold tracking-tight">
                                LP<span className="text-teal-300">shortener</span>
                            </span>
                        </a>
                        <div className="flex items-center gap-3 text-sm">
                            <span className="hidden text-teal-100/70 sm:inline">
                                {auth.user?.email}
                            </span>
                            <button
                                type="button"
                                onClick={logout}
                                className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-teal-100/80 ring-1 ring-teal-300/20 transition hover:bg-white/5"
                            >
                                <LogOut className="size-3.5" />
                                Salir
                            </button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-6 py-10">
                    <div className="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Shortened URLs
                            </h1>
                            <p className="mt-1 text-[#4d6469]">
                                Gestiona, edita o elimina tus enlaces acortados.
                            </p>
                        </div>
                        <a
                            href="/"
                            className="inline-flex items-center gap-2 text-sm font-medium text-teal-800 hover:text-teal-950"
                        >
                            <Plus className="size-4" />
                            Crear otro
                        </a>
                    </div>

                    <form
                        onSubmit={createUrl}
                        className="mb-8 flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-teal-900/5 sm:flex-row"
                    >
                        <div className="flex-1 space-y-1">
                            <Label htmlFor="new-url">Original URL</Label>
                            <Input
                                id="new-url"
                                type="url"
                                required
                                placeholder="https://…"
                                value={createForm.data.original_url}
                                onChange={(event) =>
                                    createForm.setData('original_url', event.target.value)
                                }
                            />
                            {createForm.errors.original_url ? (
                                <p className="text-sm text-rose-600">
                                    {createForm.errors.original_url}
                                </p>
                            ) : null}
                        </div>
                        <Button
                            type="submit"
                            disabled={createForm.processing}
                            className="mt-auto bg-[#0f766e] hover:bg-[#0d9488]"
                        >
                            {createForm.processing ? <Spinner /> : 'CREATE'}
                        </Button>
                    </form>

                    <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-teal-900/5">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-[#edf5f3] text-[#3d565c]">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">id</th>
                                        <th className="px-4 py-3 font-medium">code</th>
                                        <th className="px-4 py-3 font-medium">original url</th>
                                        <th className="px-4 py-3 font-medium">clicks</th>
                                        <th className="px-4 py-3 font-medium">actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {urls.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-10 text-center text-[#6b8186]"
                                            >
                                                Aún no tienes URLs. Crea la primera arriba.
                                            </td>
                                        </tr>
                                    ) : (
                                        urls.map((url) => (
                                            <tr
                                                key={url.id}
                                                className={`border-t border-teal-900/5 ${
                                                    highlightId === url.id
                                                        ? 'bg-teal-50'
                                                        : ''
                                                }`}
                                            >
                                                <td className="px-4 py-3 text-[#6b8186]">
                                                    #{url.id}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <a
                                                        href={`/${url.code}`}
                                                        className="font-mono font-medium text-teal-800 hover:underline"
                                                    >
                                                        {url.code}
                                                    </a>
                                                </td>
                                                <td className="max-w-md truncate px-4 py-3 text-[#31474c]">
                                                    {url.original_url}
                                                </td>
                                                <td className="px-4 py-3 text-[#6b8186]">
                                                    {url.clicks}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            asChild
                                                        >
                                                            <a
                                                                href={url.short_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                aria-label="Visitar"
                                                            >
                                                                <ExternalLink className="size-4" />
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => openEdit(url)}
                                                            aria-label="Editar"
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => removeUrl(url)}
                                                            aria-label="Eliminar"
                                                        >
                                                            <Trash2 className="size-4 text-rose-600" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>

                <Dialog open={Boolean(editing)} onOpenChange={(open) => !open && setEditing(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Editar URL</DialogTitle>
                            <DialogDescription>
                                Actualiza el destino de{' '}
                                <span className="font-mono">/{editing?.code}</span>. El código no
                                cambia.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={saveEdit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-url">Original URL</Label>
                                <Input
                                    id="edit-url"
                                    type="url"
                                    required
                                    value={editForm.data.original_url}
                                    onChange={(event) =>
                                        editForm.setData('original_url', event.target.value)
                                    }
                                />
                                {editForm.errors.original_url ? (
                                    <p className="text-sm text-rose-600">
                                        {editForm.errors.original_url}
                                    </p>
                                ) : null}
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditing(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    {editForm.processing ? <Spinner /> : 'Guardar'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
