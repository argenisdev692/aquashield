/**
 * The per-request CSP nonce is written into the document by the root Blade
 * view (`meta[property="csp-nonce"]`). Vite's dev client reads the same tag
 * for the styles it injects; the runtime libraries below read it via this
 * helper because they create `<style>` elements after React boots.
 */
export function getCspNonce(): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }

    return (
        document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce || undefined
    );
}
