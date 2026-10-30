# Loan Verification System

An AI-powered loan verification system for small and medium South African lenders.

Applicants complete a profile and apply for a loan, uploading three documents: an ID, a payslip and a bank statement. The system reads the documents with OCR (Tesseract), checks them against what the applicant entered, and sends the application details to a separate machine learning service, where a random forest model gives a recommendation. Applications that are unclear or flagged go to **Manual Review**, where a person makes the decision.

Live address: <https://loan-verification-system-6lxe.onrender.com>

---

## 1. Main features

**For applicants**

- Register, log in and complete an applicant profile (name, ID number, date of birth, phone number, address, employment status, monthly income and education). The application form opens once the profile has a 13-digit ID number, a date of birth and a monthly income above 0.
- Apply for a loan: amount, term, number of dependants, education, credit score band and asset values, then upload the three documents.
- See an illustrative repayment estimate (monthly repayment, total repayable, total interest) while filling in the form. The server works it out again when the application is submitted.
- Reuse documents from an earlier application (own documents, uploaded in the last 90 days) instead of uploading them again.
- Follow the status of their own applications on the dashboard.

**Checks on every application**

- **ID number:** must be 13 digits and pass the Luhn checksum. It must also appear on the ID document and match the registered date of birth.
- **Payslip:** the gross and net pay are read from the payslip and compared with the stated monthly income, and the employee name is compared with the applicant's name.
- **Bank statement:** the balance is read and compared with the stated bank assets, and the account holder name is compared with the applicant's name.
- **Affordability:** the monthly repayment is compared with the stated monthly income.
- **Duplicate files:** flags a document whose file (SHA-256 of its contents) was also submitted by another applicant.

Any document flag sends the application to Manual Review with fraud risk set to high. A failed affordability check sends it to Manual Review with fraud risk at least medium. If the model's confidence is **under 70%**, the application also goes to Manual Review.

**For administrators**

- An admin dashboard and a list of all applications.
- An application review page showing:
  - the applicant's details and the model's outcome, confidence and notes
  - the document check findings, plus the payslip figures and bank balance read by OCR
  - the uploaded documents, which can be downloaded
- Retrying the assessment for one application, or for all Pending applications.
- A feature importance page showing which inputs matter most to the model.

---

## 2. How it is built

| Part | Technology |
| --- | --- |
| Web application | Laravel 13 (PHP 8.3), Blade templates, Tailwind CSS, Alpine.js, built with Vite |
| Machine learning service | Python FastAPI app with a scikit-learn random forest model, in a separate repository: [loan-verification-ml](https://github.com/MandyMatsane/loan-verification-ml) |
| OCR | Tesseract, called through the `thiagoalessio/tesseract_ocr` PHP package |
| Database | MySQL (the tests use an in-memory SQLite database) |

The web application calls two endpoints on the ML service:

- `POST /api/ml/predict`: the recommendation for one application
- `GET /api/ml/feature-importance`: the model's feature importance

### Main service classes (`app/Services`)

| Class | What it does |
| --- | --- |
| `ApplicationAssessor` | Runs the assessment for an application: gets the model prediction, then the affordability and document checks. Retries Pending applications, either automatically or when an admin asks. |
| `MlPredictionService` | Sends the application details to the ML service, and saves the outcome, confidence, fraud risk and notes. Sends the application to Manual Review when confidence is under 70%. Also fetches feature importance. |
| `FraudCheckService` | The document checks: ID number, payslip name, payslip income, bank statement name and balance, and duplicate files. |
| `OcrService` | Reads the text of an uploaded document with Tesseract, shrinking large images first. |
| `TesseractCommand` | A small change to the Tesseract wrapper that skips an extra `tesseract --version` call on every document. |
| `PayslipIncomeExtractor` | Reads the gross pay, net pay and employee name from a payslip's text. |
| `BankStatementExtractor` | Reads the account holder and the balance from a bank statement's text. |
| `AmountParser` | Reads rand amounts as Tesseract gives them (for example `R12,345.67` or `12 345,67`). |
| `RepaymentCalculator` | The repayment estimate for a fixed-rate loan repaid in equal monthly instalments. |
| `ReusableDocuments` | Finds the earlier documents an applicant may reuse on a new application. |

Uploaded documents are stored on the private `documents` disk (`storage/app/private`). They are only served through a download route, to administrators and to the document's owner.

---

## 3. Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.3 or newer (`composer.json` requires `^8.3`), with the `pdo_mysql`, `mbstring`, `zip`, `gd`, `bcmath` and `exif` extensions (the ones the Docker image installs) |
| Composer | 2 |
| Node.js | 20.19 or newer, or 22.12 or newer (required by Vite 8 and `laravel-vite-plugin`) |
| MySQL | No version is pinned in the project |
| Tesseract OCR | No version is pinned. It needs the English language data, and the `tesseract` program must be on the `PATH`. |
| Python | 3.12 for the ML service (the version its Dockerfile uses) |

---

## 4. Setup

These steps run the web application and the ML service on your own machine.

**1. Clone the repository**

```bash
git clone https://github.com/MandyMatsane/loan-verification-system.git
cd loan-verification-system
```

**2. Install the dependencies**

```bash
composer install
npm install
```

**3. Create the environment file and the application key**

```bash
cp .env.example .env
php artisan key:generate
```

**4. Set the variables in `.env`**

The example file uses SQLite by default. For MySQL, set:

| Variable | What it does |
| --- | --- |
| `DB_CONNECTION` | Set to `mysql` |
| `DB_HOST` | MySQL server address, for example `127.0.0.1` |
| `DB_PORT` | MySQL port, usually `3306` |
| `DB_DATABASE` | Name of the database (create it first) |
| `DB_USERNAME` | MySQL user |
| `DB_PASSWORD` | That user's password |
| `MYSQL_ATTR_SSL_CA` | Path to the CA certificate, only when the MySQL server requires SSL (leave empty locally) |

For the ML service and the admin account:

| Variable | What it does |
| --- | --- |
| `ML_SERVICE_URL` | Base URL of the ML service. Locally: `http://localhost:8000` |
| `ADMIN_EMAIL` | Email address of the admin account created by `AdminSeeder`. Leave it empty to skip creating one. |
| `ADMIN_SEED_PASSWORD` | Password for that admin account. Choose your own; never commit it. |

Optional settings are listed under [Business rules](#6-business-rules).

**5. Create the tables and the admin account**

```bash
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

The seeder does nothing if `ADMIN_EMAIL` or `ADMIN_SEED_PASSWORD` is empty, and it leaves an existing account unchanged.

**6. Build the front-end assets**

```bash
npm run build
```

Or, while developing, keep `npm run dev` running instead.

**7. Start the web application**

```bash
php artisan serve
```

The app is then at <http://localhost:8000> by default. That is the same port the ML service uses below, so start one of them on another port. For example, `php artisan serve --port=8080` keeps `ML_SERVICE_URL=http://localhost:8000` working.

`composer run dev` starts the server, a queue listener, the log viewer and Vite together. It also uses Laravel's default port 8000.

**8. Start the ML service**

The ML service lives in its own repository. Its Dockerfile starts it with `uvicorn main:app`, so locally:

```bash
git clone https://github.com/MandyMatsane/loan-verification-ml.git
cd loan-verification-ml
python -m venv venv
source venv/bin/activate        # on Windows: venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8000
```

The trained model (`loan_model.pkl`) is in that repository, and `train_model.py` rebuilds it from `loan_approval_dataset.csv`.

If the ML service is not running, new applications stay **Pending** until an assessment is retried.

---

## 5. Running the tests

```bash
php artisan test
```

`composer test` also works; it clears the cached configuration first.

The tests use an in-memory SQLite database (set in `phpunit.xml`), so they don't need MySQL. They don't call the real ML service (its responses are faked), and most of them don't run Tesseract (the OCR result is mocked).

They cover:

- registration, login, password reset and email verification
- the profile, including the ID number, phone number and education rules
- the loan limits and the repayment estimate
- the ML service call and the 70% confidence rule
- each document check: ID, payslip income and name, bank statement, duplicate files
- affordability
- document reuse
- retrying assessments
- the admin review pages
- the OCR speed changes
- page wording and layout

---

## 6. Business rules

These settings are in `config/loan.php`.

| Setting | Default | Meaning |
| --- | --- | --- |
| `annual_interest_rate` | `0.20` (20% a year) | Used only for the repayment estimate. It is an illustrative rate, not a real lender's rate. Set with `LOAN_ANNUAL_INTEREST_RATE`. |
| `max_amount` | `100000` | Largest amount (in rand) an applicant may request |
| `min_term` | `1` | Shortest loan term, in months |
| `max_term` | `60` | Longest loan term, in months |
| `income_tolerance` | `0.15` | How far the stated monthly income may be from the closest payslip figure (as a fraction of that figure) before it is flagged |
| `max_repayment_ratio` | `0.35` | Largest monthly repayment, as a fraction of stated monthly income, before the affordability check flags it |
| `bank_balance_tolerance` | `0.20` | How far the stated bank assets may be from the bank statement balance (as a fraction of the balance) before it is flagged |
| `bank_statement_name_similarity` | `85` | Lowest name similarity (0 to 100) for the bank statement name to count as the applicant's |
| `payslip_name_similarity` | `85` | Lowest name similarity (0 to 100) for the payslip name to count as the applicant's |
| `duplicate_check_enabled` | `true` | Turns the duplicate file check on or off. Set with `LOAN_DUPLICATE_CHECK` (for example `false` for demos where several test users upload the same sample files). |

Two more rules are set in code rather than in `config/loan.php`:

- **Confidence threshold:** a model confidence under 70% means Manual Review (`MlPredictionService`).
- **Reuse window:** earlier documents can be reused for 90 days (`ReusableDocuments`).

---

## 7. Deployment

The live app runs on **Render** as a Docker web service, with a **MySQL database on Aiven**.

- **Dockerfile:**
  - It builds the front-end assets with Node 20, then sets up PHP 8.3 with Apache.
  - It installs Tesseract (`tesseract-ocr` and `tesseract-ocr-eng`) and turns on OPcache.
  - It limits Tesseract to one thread (`OMP_THREAD_LIMIT=1`), which is much faster on Render's small shared CPU.
- **Start script (`docker/start.sh`):** it runs on every container start. In order, it:
  1. points Apache at Render's `PORT`
  2. creates the storage folders
  3. links public storage
  4. runs `php artisan migrate --force`
  5. runs the `AdminSeeder`
  6. caches the configuration, routes and views
  7. starts Apache
- **Database:** Aiven requires SSL. Its CA certificate is in the repository at `certs/aiven-ca.pem`, and `MYSQL_ATTR_SSL_CA` points to it. Inside the image that path is `/var/www/html/certs/aiven-ca.pem`.
- **Environment variables:** these are set in Render, never committed:
  - the app key
  - the database settings
  - `ML_SERVICE_URL`, pointing at the deployed ML service
  - the admin seed variables
- **ML service:** deployed separately from its own repository and Dockerfile.

**Free tier sleeping:** free-tier services sleep when idle, so the first request after a quiet period can be slow while the service wakes up. If the ML service is still asleep when an application is submitted, the prediction can time out and the application is left **Pending**. It is then retried in two ways:

- **Automatically,** when someone opens the dashboard: one application at a time, at least 2 minutes apart, up to 5 attempts.
- **By an administrator,** from the admin pages, for one application or for all Pending ones.

---

## 8. Known limitations

- **Self-reported answers are not verified:**
  - the credit score band, number of dependants, education and asset values (residential, commercial and luxury) come from the applicant and are not checked against any source
  - only the bank assets are compared with a document, the bank statement
  - the credit band is turned into a representative score before it is sent to the model
- **Free-tier speed:** Render's free tier sleeps when idle and has a very small CPU. The first request is slow, and OCR takes several seconds per document.
- **Storage on Render is not persistent:** uploaded documents are saved on the container's own disk, so they are lost when the service restarts or is redeployed. The database records remain, but the files they point to are gone.
- **The OCR checks depend on image quality:** blurred or unusual documents may not be read correctly and will then be flagged for manual review.
