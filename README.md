# ArbitriGolf

Golf referee management system - Sistema di gestione per arbitri di golf.

## Overview / Panoramica

ArbitriGolf is a comprehensive management system for golf referees, designed to streamline tournament assignments, referee scheduling, and administrative tasks. This project represents an incremental migration from a legacy system to a more structured and maintainable Laravel-based solution.

ArbitriGolf è un sistema completo di gestione per arbitri di golf, progettato per semplificare le assegnazioni ai tornei, la pianificazione degli arbitri e le attività amministrative. Questo progetto rappresenta una migrazione incrementale da un sistema legacy verso una soluzione basata su Laravel più strutturata e manutenibile.

## Key Features / Funzionalità Principali

- 📅 Tournament management / Gestione tornei
- 👥 Referee database and profiles / Database arbitri e profili
- 📋 Assignment system / Sistema di assegnazioni
- 🏌️ Golf club management / Gestione circoli golf
- 📊 Reporting and statistics / Report e statistiche
- 🔐 Secure authentication / Autenticazione sicura


## Installation / Installazione

```bash
# Clone the repository / Clonare il repository
git clone https://github.com/nelson906/arbitrigolf.git
cd arbitrigolf

# Install dependencies / Installare le dipendenze
composer install
npm install

# Configure .env file / Configurare il file .env
cp .env.example .env
php artisan key:generate

# Run migrations / Eseguire le migrazioni
php artisan migrate

# Build assets / Compilare gli asset
npm run build
```

## Project Structure / Struttura del Progetto

The project follows the standard Laravel structure with some customizations:
Il progetto segue la struttura standard di Laravel con alcune personalizzazioni:

```
├── app/
│   ├── Models/          # Eloquent models / Modelli Eloquent
│   ├── Http/
│   │   └── Controllers/ # Application logic / Logica applicativa
├── resources/
│   └── views/           # Blade views / Viste Blade
├── routes/              # Route definitions / Definizione delle route
├── database/
│   └── migrations/      # Database migrations / Migrazioni database
```

## Important Notes / Note Importanti

- Cannot run `php artisan tinker` in this environment / Non è possibile eseguire `php artisan tinker` in questo ambiente
- For document generation, use the gestione-arbitri system / Per la generazione di documenti, utilizzare il sistema gestione-arbitri
- Report issues or unclear situations with suggestions on how to address them / Segnalare problemi o situazioni poco chiare con suggerimenti su come affrontarli

## Contributing / Contribuire

Contributions are welcome! Please feel free to submit a Pull Request.
I contributi sono benvenuti! Sentiti libero di inviare una Pull Request.

## License / Licenza

This project is open-sourced software licensed under the [MIT license](LICENSE).
Questo progetto è un software open source rilasciato sotto [licenza MIT](LICENSE).

## Contact / Contatti

For questions or support, please open an issue in the GitHub repository.
Per domande o supporto, apri una issue nel repository GitHub.