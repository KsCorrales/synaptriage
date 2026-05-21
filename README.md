# SynapTriage

> Laravel 11 application for support ticket auto-triage, integrating with SynapCores AIDB via a custom SDK.

---

## Overview

SynapTriage automatically classifies and prioritizes incoming support tickets using a machine learning model trained on historical ticket data. The system exposes two complementary flows:

1. **Batch Training Flow** — Before the application can predict anything, the model needs to be taught. This is a one-time (or periodic) offline step: Artisan commands seed a realistic dataset of historically resolved tickets into SynapCores AIDB, create an experiment, and train the model on that data. Once trained, SynapCores understands the relationship between ticket features (category, keywords, customer tier, etc.) and the correct priority. Think of this as the "teach before you deploy" phase — in a real production system this would draw from years of resolved tickets as training data. After training, `AUTOML.PREDICT` is run against the full seeded dataset to backfill predictions on historical records.

2. **Async Prediction Flow** — Once the model is trained, every new ticket created through the UI triggers a Laravel Queue Job. The Job calls the SynapCores SDK in the background, retrieves the predicted priority and confidence score, and persists the result. The HTTP response is never blocked waiting on a third-party API call.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.2+ |
| Frontend | Vue 3 + Inertia.js |
| Database | SQLite (local) |
| Queue | Laravel Queue (database driver) |
| ML Backend | SynapCores AIDB (custom SDK) |
| Testing | PHPUnit |

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                      UI (Vue 3)                      │
│          Ticket list + predicted priority            │
└────────────────────────┬────────────────────────────┘
                         │ Inertia
┌────────────────────────▼────────────────────────────┐
│               TicketController (thin)                │
│         store() → dispatch Job → return ticket       │
└───────────┬─────────────────────────┬───────────────┘
            │                         │
┌───────────▼──────────┐   ┌──────────▼──────────────┐
│  ProcessTicketTriage  │   │    SynapCoresService     │
│       (Job)           │──▶│  createExperiment()      │
│  Queued async         │   │  train()                 │
└───────────────────────┘   │  predict()               │
                            └──────────┬───────────────┘
                                       │
                            ┌──────────▼───────────────┐
                            │    SynapCoresClient       │
                            │  JWT auth + cache         │
                            │  Retry on 401             │
                            │  Typed error handling     │
                            └──────────────────────────┘
```

### Key Design Decisions

- **Thin controllers:** `TicketController` only handles HTTP — validation, dispatch, and response. All ML logic lives in `SynapCoresService`.
- **SDK separation of concerns:** `SynapCoresClient` owns the transport layer (auth, retries, timeouts) and knows nothing about tickets or experiments. `SynapCoresService` owns the business logic (experiment lifecycle, prediction mapping) and knows nothing about HTTP. Each class has exactly one reason to change.
- **JWT auth caching:** The token is stored in Laravel Cache with a TTL 60 seconds shorter than the actual expiry to avoid edge-case expirations mid-request. If a 401 is received despite a cached token, the cache is invalidated and a single re-authentication attempt is made transparently — the caller never needs to handle token lifecycle.
- **Typed error handling:** All failure modes (authentication failure, timeout, non-2xx responses) are caught and re-thrown as a `SynapCoresException` with a specific status code and context. This means the rest of the application has a single, predictable exception type to handle — no leaking of HTTP client internals.
- **Async by default:** Predictions run in a queued Job so the HTTP response is never blocked by a third-party API call.
- **DTOs over arrays:** `PredictionResult` and `ExperimentConfig` are typed value objects — no magic array keys passed between layers, and the compiler catches contract mismatches early.

---

## Folder Structure

```
app/
├── Console/Commands/
│   ├── SeedSynapExperiment.php     # Creates experiment + trains model
│   └── RunSynapAutoML.php          # Runs AUTOML.PREDICT on dataset
├── Http/Controllers/
│   └── TicketController.php
├── Jobs/
│   └── ProcessTicketTriage.php
├── Models/
│   └── SupportTicket.php
└── Services/
    └── SynapCores/
        ├── SynapCoresClient.php    # Transport layer (JWT, retries, timeouts)
        ├── SynapCoresService.php   # Business logic layer
        ├── DTOs/
        │   ├── PredictionResult.php
        │   └── ExperimentConfig.php
        └── Exceptions/
            └── SynapCoresException.php
```

---

## Setup

### Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- A SynapCores AIDB account with API key

### Installation

```bash
git clone https://github.com/your-username/synaptriage.git
cd synaptriage

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

### Environment

Edit `.env` and set your SynapCores credentials:

```env
SYNAPCORES_BASE_URL=https://api.synapcores.com
SYNAPCORES_API_KEY=your-api-key-here
SYNAPCORES_TIMEOUT=30
SYNAPCORES_JWT_TTL=3600
```

### Database

```bash
php artisan migrate
```

### Queue Worker

```bash
php artisan queue:work
```

Open a separate terminal and keep this running while using the app.

---

## Artisan Commands

### Seed the dataset and train the model

```bash
php artisan synap:seed-experiment
```

This is the **training phase** — run it once before using the app for predictions.

The model has no knowledge of your domain out of the box. This command teaches it by example: it generates thousands of realistic tickets with known, correct priorities (the "ground truth"), sends them to SynapCores, and lets the AIDB find the patterns. After this command completes successfully, the model is ready to classify tickets it has never seen before.

Steps executed:
1. Seeds 5,000–10,000 realistic support tickets with intentional signal into the local DB
2. Sends the dataset to SynapCores via `CREATE EXPERIMENT`
3. Executes `TRAIN` on the experiment
4. Outputs the `experiment_id` — save this, it is stored in `.env` and used by all subsequent commands

### Run AutoML predictions on the full dataset

```bash
php artisan synap:run-automl
```

This command:
1. Fetches all tickets without a prediction
2. Calls `AUTOML.PREDICT` in batches
3. Persists `predicted_priority` and `confidence_score` back to the DB

---

## Using the App

```bash
php artisan serve
```

Visit `http://localhost:8000`. You will see a table of support tickets with:

| Column | Description |
|---|---|
| Subject | Ticket subject |
| Category | Billing, Technical, General, etc. |
| Predicted Priority | Low / Medium / High / Critical |
| Confidence | Model confidence score (0–1) |
| Status | Pending prediction / Complete |

Creating a new ticket dispatches `ProcessTicketTriage` to the queue. Once the worker processes it, the prediction appears automatically.

---

## ML Signal Design

The seeded dataset is deliberately structured so the model has a learnable signal:

| Feature | Signal |
|---|---|
| `category` | `billing` and `outage` tickets skew toward High/Critical |
| `subject_keywords` | Words like "urgent", "down", "broken" correlate with Critical |
| `response_time_expectation` | Short SLA expectation → higher predicted priority |
| `customer_tier` | Enterprise customers skew toward higher priority |
| `created_at` hour | Off-hours tickets slightly upweighted |

Without an intentional signal, the model would learn noise and predictions would be meaningless — defeating the purpose of the exercise.

---

## Cut Corners & What I Would Do With More Time

This section is intentional. A senior engineer ships pragmatically and documents trade-offs honestly.

### What was cut

- **No authentication/authorization** — There is no login system. In production, tickets would be scoped to authenticated users or organizations.
- **SQLite instead of MySQL** — Chosen for zero-config portability so reviewers can run the project without a database server. Production would use MySQL or PostgreSQL with proper connection pooling.
- **No retry/backoff on failed Jobs** — `ProcessTicketTriage` has basic error handling but no exponential backoff strategy. Laravel's built-in `tries` and `backoff` properties would be configured properly in production.
- **No pagination on the ticket table** — The UI loads all tickets. With 10,000 rows this would need server-side pagination via Inertia's built-in support.
- **No feature importance or model explainability** — We show confidence scores but not which features drove the prediction. A production system would expose this from SynapCores' API response.
- **Single queue, no priority lanes** — All jobs go to the `default` queue. A production system would have separate `critical` and `default` queues with different worker concurrency.
- **No test coverage on the SDK** — The `SynapCoresClient` would benefit from a full suite of unit tests mocking HTTP responses, especially for the JWT re-auth path.
- **No frontend architecture** — Tailwind utility classes are applied inline directly in the component for speed. With more time, this would be extracted into a proper design system, reusable component library, and consistent styling conventions. The frontend was intentionally kept minimal as it is not the focus of this assessment.

### What I would do with more time

- Add feature flags to A/B test different SynapCores experiment configurations
- Implement a feedback loop: agents can mark predictions as correct/incorrect, feeding a retraining pipeline
- Extract the SDK into a standalone Composer package with its own test suite and documentation
- Add Horizon for queue monitoring and visibility
- Implement webhook support so SynapCores can push prediction results instead of polling

---

## License

MIT