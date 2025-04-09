# 🏥 Claims Processing System

## 📘 Overview
This project is a healthcare claims processing system designed to efficiently manage the **submission**, **batching**, and **processing** of medical claims between healthcare providers and insurance companies. It streamlines the workflow from claim submission to intelligent batch optimization in order to **minimize processing costs**.

---

## ✨ Key Features

- **User Authentication & Authorization**  
  Role-based access control for providers and administrators

- **Claim Submission**  
  Submit medical claims with line items, specialties, and priority levels

- **Smart Batching Algorithm**  
  Automatically groups claims into batches based on multiple factors

- **Batch Optimization**  
  Rebalances batches to minimize processing costs before submission

- **Processing Scheduling**  
  Manages insurer daily capacity and schedules batch processing accordingly

- **Notifications**  
  Email notifications to insurers when new batches are created

---

## 🏗️ System Architecture

The application is built on **Laravel 11**, using:

- [Laravel Breeze](https://laravel.com/docs/starter-kits#laravel-breeze) for authentication scaffolding  
- [Inertia.js](https://inertiajs.com/) and **Vue 3** for the frontend  
- **SQLite** database (for development)

---

## 🧩 Core Models

| Model        | Description                                      |
|--------------|--------------------------------------------------|
| `User`       | Healthcare provider staff or administrators      |
| `Provider`   | Healthcare organizations submitting claims       |
| `Insurer`    | Insurance companies receiving claims             |
| `Specialty`  | Medical specialties (e.g., Cardiology, Ortho)    |
| `Claim`      | Individual claim submissions with metadata       |
| `ClaimItem`  | Line items within a claim                        |
| `Batch`      | Groups of claims for processing                  |

---

## 🧠 Batching Algorithm

The **intelligent batching system** is the core feature of the application, designed to optimize processing efficiency and cost.

### 🔄 Batch Assignment

- Claims are grouped by **provider**, **insurer**, and **date**
- Each insurer has configurable **min/max batch size limits**
- Batches respect insurer preferences for grouping by:
  - Encounter date  
  - Submission date

---

### 💰 Cost Calculation Factors

Each batch’s **processing cost** is calculated using:

- **Time of month** – late-month claims are more costly
- **Specialty efficiency** – some insurers process certain specialties more efficiently
- **Priority level** – higher priority claims are costlier
- **Monetary value** – higher value = higher cost
- **Batch size optimization** – closer to optimal size = cheaper

---

### 🔧 Batch Optimization

Before processing, the system:

- **Merges** small batches
- **Splits** large batches
- **Redistributes** claims for cost-effective batch sizing

---

### ⏳ Processing Scheduling

- Respects insurer **daily processing capacity**
- Automatically schedules **overflow** to future days
- Maintains **FIFO** (First-In-First-Out) order

---

## 🔍 Algorithm Implementation Details

```php
// When a new claim is created:
1. Find existing open batch by provider/insurer/date
2. If none, create a new batch
3. Add the claim to batch
4. Update batch totals and recalculate cost
5. If batch exceeds max size, create a new one

// Cost Calculation:
1. Apply time-of-month multiplier
2. Factor in specialty efficiency
3. Apply claim priority multiplier
4. Scale cost by monetary value
5. Optimize based on batch size

// Batch Optimization:
1. Identify under/oversized batches
2. Merge small batches or reassign claims
3. Split large batches if needed
4. Recalculate cost after adjustment
```
Setup

Clone the repository
Install dependencies:
Copycomposer install
npm install

Set up your environment:
Copycp .env.example .env
php artisan key:generate
touch database/database.sqlite

Run migrations and seed:
Copyphp artisan migrate
php artisan db:seed

Run the development server:
Copynpm run dev
php artisan serve


Development Notes

The application uses Laravel's standard MVC architecture
Inertia.js provides a seamless SPA experience while using server-side routing
The batching algorithm is encapsulated in a dedicated service class for maintainability
Database transactions ensure data consistency during batch operations
The system is designed with scalability in mind, using chunking for processing large datasets

API Routes
The application provides RESTful routes for claims and batches management:

/claims - List and create claims
/claims/{id} - View claim details
/claims/create - Submit a new claim
/batches - View all batches
/batches/{id} - View batch details

Command Line Interface
The application includes artisan commands for batch management:

php artisan claims:process-batches - Process all ready batches
php artisan claims:optimize-batches - Rebalance batches to minimize costs
