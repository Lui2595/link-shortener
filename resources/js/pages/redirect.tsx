import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';

type RedirectProps = {
    originalUrl: string;
    code: string;
};

export default function RedirectPage({ originalUrl, code }: RedirectProps) {
    const [seconds, setSeconds] = useState(1);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            window.location.replace(originalUrl);
        }, 1200);

        const countdown = window.setInterval(() => {
            setSeconds((value) => Math.max(0, value - 1));
        }, 1000);

        return () => {
            window.clearTimeout(timer);
            window.clearInterval(countdown);
        };
    }, [originalUrl]);

    return (
        <>
            <Head title="Redirecting…" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#071a1f] px-6 text-center text-[#e8f4f2]">
                <div className="mb-6 flex size-14 items-center justify-center rounded-full bg-teal-400/15 ring-1 ring-teal-300/30">
                    <Spinner className="size-6 text-teal-300" />
                </div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Wait a moment…
                </h1>
                <p className="mt-2 max-w-md text-teal-100/70">
                    Redirigiendo{' '}
                    <span className="font-mono text-teal-200">/{code}</span> a
                    tu destino{seconds > 0 ? ` en ${seconds}s` : ''}.
                </p>
                <a
                    href={originalUrl}
                    className="mt-6 text-sm text-teal-300 underline-offset-4 hover:underline"
                >
                    Continuar ahora
                </a>
            </div>
        </>
    );
}
