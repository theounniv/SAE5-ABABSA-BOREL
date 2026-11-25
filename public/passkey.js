const bouton = document.querySelector('#ajouter-passkey');
const resultat = document.querySelector('#resultat-passkey');

bouton.addEventListener('click', async () => {
    if (!window.PublicKeyCredential?.parseCreationOptionsFromJSON) {
        resultat.textContent = 'Ce navigateur ne prend pas en charge l’ajout de passkeys.';
        return;
    }

    bouton.disabled = true;
    resultat.textContent = 'Suivez les instructions de votre navigateur.';
    const entetes = { 'X-CSRF-Token': bouton.dataset.csrf };

    try {
        const options = await fetch(bouton.dataset.options, { method: 'POST', headers: entetes });
        if (!options.ok) {
            const { erreur } = await options.json();
            throw new Error(erreur ?? 'Impossible de préparer l’ajout de la passkey.');
        }

        const publicKey = PublicKeyCredential.parseCreationOptionsFromJSON(await options.json());
        const identifiant = await navigator.credentials.create({ publicKey });
        const { response } = identifiant.toJSON();
        const enregistrement = await fetch(bouton.dataset.enregistrement, {
            method: 'POST',
            headers: { ...entetes, 'Content-Type': 'application/json' },
            body: JSON.stringify(response),
        });

        if (!enregistrement.ok) {
            const { erreur } = await enregistrement.json();
            throw new Error(erreur ?? 'Impossible d’enregistrer la passkey.');
        }

        resultat.textContent = 'Passkey enregistrée.';
    } catch (erreur) {
        resultat.textContent = erreur.name === 'NotAllowedError' ? 'Ajout de la passkey annulé.' : erreur.message;
    } finally {
        bouton.disabled = false;
    }
});
