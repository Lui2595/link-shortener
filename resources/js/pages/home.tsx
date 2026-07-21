import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Link2, LoaderCircle, LogOut } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { api } from '@/lib/api';
import type { Auth } from '@/types/auth';

type HomeProps = {
    pendingUrl?: string | null;
    otpRequired?: boolean;
};

type PageProps = {
    auth: Auth;
    flash: {
        success?: string | null;
        otp_required?: boolean;
    };
};

export default function Home({ pendingUrl, otpRequired }: HomeProps) {
    const { auth, flash } = usePage<PageProps>().props;
    const form = useForm({ original_url: pendingUrl ?? '' });
    const [otpOpen, setOtpOpen] = useState(Boolean(otpRequired || flash.otp_required));
    const [step, setStep] = useState<'email' | 'code'>('email');
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [otpLoading, setOtpLoading] = useState(false);

    useEffect(() => {
        if (otpRequired || flash.otp_required) {
            setOtpOpen(true);
        }
    }, [otpRequired, flash.otp_required]);

    function submitUrl(event: FormEvent) {
        event.preventDefault();
        form.post('/shorten', {
            preserveScroll: true,
            onSuccess: () => {
                if (!auth.user) {
                    setOtpOpen(true);
                    setStep('email');
                }
            },
        });
    }

    async function requestOtp(event: FormEvent) {
        event.preventDefault();
        setOtpLoading(true);

        try {
            await api('/api/auth/otp/request', {
                method: 'POST',
                body: JSON.stringify({ email }),
            });
            setStep('code');
            toast.success('Revisa tu correo: te enviamos un código de acceso.');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'No se pudo enviar el código.');
        } finally {
            setOtpLoading(false);
        }
    }

    async function verifyOtp(event: FormEvent) {
        event.preventDefault();
        setOtpLoading(true);

        try {
            await api('/api/auth/otp/verify', {
                method: 'POST',
                body: JSON.stringify({ email, code }),
            });

            toast.success('Sesión iniciada.');
            setOtpOpen(false);

            router.post('/urls/commit-pending');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Código inválido.');
        } finally {
            setOtpLoading(false);
        }
    }

    return (
        <>
            <Head title="Short your link" />
            <div className="relative min-h-screen overflow-hidden bg-[#071a1f] text-[#e8f4f2]">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(45,212,191,0.18),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(14,116,144,0.25),_transparent_45%)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 opacity-[0.08]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                <header className="relative z-10 mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-2">
                        <span className="flex size-9 items-center justify-center rounded-lg bg-teal-400/15 text-teal-300 ring-1 ring-teal-300/30">
                            <Link2 className="size-4" />
                        </span>
                        <span className="text-lg font-semibold tracking-tight">
                            LP<span className="text-teal-300">shortener</span>
                        </span>
                    </div>
                    <div className="flex items-center gap-3 text-sm">
                        <a
                            href="/api/documentation"
                            className="text-teal-100/70 transition hover:text-teal-200"
                        >
                            API Docs
                        </a>
                        {auth.user ? (
                            <>
                                <a
                                    href="/urls"
                                    className="rounded-md bg-teal-400/15 px-3 py-1.5 text-teal-100 ring-1 ring-teal-300/25 transition hover:bg-teal-400/25"
                                >
                                    Mis URLs
                                </a>
                                <button
                                    type="button"
                                    onClick={() => {
                                        void (async () => {
                                            try {
                                                await api('/api/auth/logout', {
                                                    method: 'POST',
                                                });
                                            } finally {
                                                router.visit('/');
                                            }
                                        })();
                                    }}
                                    className="inline-flex items-center gap-1.5 text-teal-100/70 transition hover:text-teal-100"
                                >
                                    <LogOut className="size-3.5" />
                                    Salir
                                </button>
                            </>
                        ) : null}
                    </div>
                </header>

                <main className="relative z-10 mx-auto flex min-h-[calc(100vh-5.5rem)] w-full max-w-3xl flex-col justify-center px-6 pb-20">
                    <p className="mb-3 text-sm font-medium tracking-[0.2em] text-teal-300/80 uppercase">
                        LPshortener
                    </p>
                    <h1 className="max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        Acorta tu Link
                    </h1>
                    <p className="mt-4 max-w-xl text-base text-teal-50/70 sm:text-lg">
                        Acorta URLs con códigos seguros de 8 caracteres. Sin contraseña:
                        inicia sesión con un código enviado a tu correo.
                    </p>

                    <form
                        onSubmit={submitUrl}
                        className="mt-10 flex w-full flex-col gap-3 sm:flex-row sm:items-start"
                    >
                        <div className="flex-1 space-y-2">
                            <Label htmlFor="original_url" className="sr-only">
                                Original URL
                            </Label>
                            <Input
                                id="original_url"
                                type="url"
                                required
                                placeholder="https://example.com/tu-enlace-largo"
                                value={form.data.original_url}
                                onChange={(event) =>
                                    form.setData('original_url', event.target.value)
                                }
                                className="h-12 border-teal-200/20 bg-white/5 text-base text-white placeholder:text-teal-100/35 focus-visible:ring-teal-300/40"
                            />
                            {form.errors.original_url ? (
                                <p className="text-sm text-rose-300">{form.errors.original_url}</p>
                            ) : null}
                        </div>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="h-12 bg-teal-400 px-6 text-base font-semibold text-[#062024] hover:bg-teal-300"
                        >
                            {form.processing ? <Spinner /> : 'Acortar'}
                        </Button>
                    </form>
                </main>

                <Dialog open={otpOpen} onOpenChange={setOtpOpen}>
                    <DialogContent className="border-teal-900/40 bg-[#0b2228] text-teal-50 sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Accede con tu correo</DialogTitle>
                            <DialogDescription className="text-teal-100/65">
                                Te enviaremos un código de un solo uso para guardar tu enlace
                                acortado.
                            </DialogDescription>
                        </DialogHeader>

                        {step === 'email' ? (
                            <form onSubmit={requestOtp} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        value={email}
                                        onChange={(event) => setEmail(event.target.value)}
                                        placeholder="tu@empresa.com"
                                        className="border-teal-200/20 bg-white/5"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={otpLoading}
                                    className="w-full bg-teal-400 text-[#062024] hover:bg-teal-300"
                                >
                                    {otpLoading ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        'Enviar código'
                                    )}
                                </Button>
                            </form>
                        ) : (
                            <form onSubmit={verifyOtp} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="otp">Código de 6 dígitos</Label>
                                    <InputOTP
                                        maxLength={6}
                                        value={code}
                                        onChange={setCode}
                                        containerClassName="justify-center"
                                    >
                                        <InputOTPGroup>
                                            {Array.from({ length: 6 }).map((_, index) => (
                                                <InputOTPSlot key={index} index={index} />
                                            ))}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={otpLoading || code.length !== 6}
                                    className="w-full bg-teal-400 text-[#062024] hover:bg-teal-300"
                                >
                                    {otpLoading ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        'Verificar y continuar'
                                    )}
                                </Button>
                                <button
                                    type="button"
                                    className="w-full text-sm text-teal-200/70 hover:text-teal-100"
                                    onClick={() => setStep('email')}
                                >
                                    Usar otro correo
                                </button>
                            </form>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
