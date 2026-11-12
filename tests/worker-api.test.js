import assert from 'node:assert/strict';
import { test } from 'node:test';
import { File } from 'node:buffer';
import { compare, hash } from 'bcryptjs';
import worker from '../src/index.js';

class MockD1 {
    users = [];
    sessions = new Map();
    transactions = [];
    sales = [];
    saleNotes = [];
    payments = [];
    showroomExpenses = [];
    availableVehicles = [];
    vehiclePhotos = [];
    notaSettings = null;
    failSales = false;
    failVehiclePhotoInsert = false;
    nextUserId = 1;
    nextExpenseId = 1;

    prepare(query) {
        let parameters = [];

        return {
            bind: (...values) => {
                const placeholderCount = (query.match(/\?/g) ?? []).length;

                if (values.length !== placeholderCount) {
                    throw new Error(
                        `D1 binding count mismatch: expected ${placeholderCount}, received ${values.length}.`,
                    );
                }

                parameters = values;

                return this.prepareStatement(query, () => parameters);
            },
            first: () => this.first(query, parameters),
            run: () => this.run(query, parameters),
            all: () => this.all(query, parameters),
        };
    }

    prepareStatement(query, getParameters) {
        return {
            first: () => this.first(query, getParameters()),
            run: () => this.run(query, getParameters()),
            all: () => this.all(query, getParameters()),
        };
    }

    async first(query, parameters) {
        if (query.includes('SELECT 1 AS connected')) {
            return { connected: 1 };
        }

        if (query.includes('FROM nota_settings') && query.includes('WHERE id = 1')) {
            return this.notaSettings;
        }

        if (query.includes('FROM showroom_expenses') && query.includes('SUM(amount)')) {
            const [startDate, endDate] = parameters;
            return {
                total_amount: this.showroomExpenses
                    .filter((expense) => expense.expense_date >= startDate && expense.expense_date <= endDate)
                    .reduce((total, expense) => total + Number(expense.amount), 0),
            };
        }

        if (query.includes('FROM showroom_expenses') && query.includes('WHERE id = ?')) {
            return this.showroomExpenses.find((expense) =>
                expense.id === parameters[0]
                && (!query.includes('AND receipt_path = ?') || expense.receipt_path === parameters[1])) ?? null;
        }

        if (query.includes('FROM vehicle_photos')
            && query.includes('car_transaction_id = ? AND path IN (')
            && query.includes('UNION ALL')
            && query.includes('WHERE id = ? AND vehicle_photo_path IN (')) {
            const pathCount = ((query.match(/\?/g) ?? []).length - 2) / 2;
            const photoPaths = parameters.slice(1, pathCount + 1);
            const transactionId = parameters[pathCount + 1];
            const vehiclePaths = parameters.slice(pathCount + 2);
            const photo = this.vehiclePhotos.find((item) =>
                item.car_transaction_id === parameters[0] && photoPaths.includes(item.path));
            const transaction = this.transactions.find((item) =>
                item.id === transactionId && vehiclePaths.includes(item.vehicle_photo_path));
            const path = photo?.path ?? transaction?.vehicle_photo_path;

            return path ? { path } : null;
        }

        if (query.includes('FROM sale_notes')
            && query.includes('INNER JOIN car_transactions')
            && query.includes('WHERE sale_notes.id = ?')) {
            const note = this.saleNotes.find((candidate) =>
                candidate.id === parameters[0]
                && (!query.includes('sale_notes.created_by = ?')
                    || candidate.created_by === parameters[1]));
            const vehicle = this.transactions.find((candidate) =>
                candidate.id === note?.car_transaction_id);

            if (!note || !vehicle) {
                return null;
            }

            const totalPaid = Number(vehicle.down_payment ?? 0)
                + Number(vehicle.leasing_disbursement ?? 0)
                + Number(vehicle.settlement_amount ?? 0);

            return {
                ...vehicle,
                ...note,
                id: note.id,
                sale_note_id: note.id,
                car_transaction_id: vehicle.id,
                payment_status: Number(vehicle.remaining_payment ?? 0) <= 0
                    ? 'paid'
                    : Number(vehicle.leasing_disbursement ?? 0) > 0
                        || this.payments.some((payment) =>
                            payment.sale_note_id === note.id && payment.payment_method === 'leasing')
                        ? 'leasing'
                        : 'unpaid',
                total_paid: totalPaid,
            };
        }

        if (query.includes('FROM sale_notes')
            && query.includes('WHERE car_transaction_id = ?')) {
            return this.saleNotes.find((note) =>
                note.car_transaction_id === parameters[0]
                && (!query.includes('created_by = ?') || note.created_by === parameters[1])) ?? null;
        }

        if (query.includes('SELECT * FROM car_transactions WHERE id = ?')
            || query.includes('SELECT id, vehicle_photo_path FROM car_transactions WHERE id = ?')) {
            return this.transactions.find((transaction) => transaction.id === parameters[0]) ?? null;
        }

        if (query.includes('FROM car_transactions')
            && query.includes('AND NOT EXISTS')
            && query.includes('WHERE id = ?')) {
            return this.transactions.find((transaction) =>
                transaction.id === parameters[0]
                && !transaction.sell_date
                && !this.saleNotes.some((note) => note.car_transaction_id === transaction.id)) ?? null;
        }

        if (query.includes('SELECT id, intertine_expenses FROM car_transactions')) {
            const vehicle = this.transactions.find((transaction) =>
                transaction.id === parameters[0]
                && !transaction.sell_date
                && !this.saleNotes.some((note) => note.car_transaction_id === transaction.id)
                && !this.sales.some((sale) => sale.car_transaction_id === transaction.id));

            return vehicle
                ? { id: vehicle.id, intertine_expenses: vehicle.intertine_expenses }
                : null;
        }

        if (query.includes("SELECT COUNT(*) AS total FROM users WHERE role = 'master'")) {
            return { total: this.users.filter((user) => user.role === 'master').length };
        }

        if (query.includes('SELECT COUNT(*) AS total FROM users')) {
            return { total: this.filteredUsers(query, parameters).length };
        }

        if (query.includes('SELECT id, role FROM users WHERE id = ?')) {
            const user = this.users.find((candidate) => candidate.id === parameters[0]);

            return user ? { id: user.id, role: user.role } : null;
        }

        if (query.includes('SELECT id FROM users WHERE username = ? AND id != ?')) {
            const user = this.users.find((candidate) =>
                candidate.username === parameters[0] && candidate.id !== parameters[1]);

            return user ? { id: user.id } : null;
        }

        if (query.includes('SELECT id FROM users WHERE username = ?')) {
            const user = this.users.find((candidate) => candidate.username === parameters[0]);

            return user ? { id: user.id } : null;
        }

        if (query.includes('SELECT password FROM users WHERE id = ?')) {
            const user = this.users.find((candidate) => candidate.id === parameters[0]);

            return user ? { password: user.password } : null;
        }

        if (query.includes('SELECT * FROM users WHERE username = ?')) {
            return this.users.find((user) => user.username === parameters[0]) ?? null;
        }

        if (query.includes('INSERT INTO users')) {
            const [name, username, password, role, createdAt, updatedAt] = parameters;
            const id = Math.max(0, ...this.users.map((user) => user.id)) + 1;
            const user = { id, name, username, password, role, created_at: createdAt, updated_at: updatedAt };
            this.users.push(user);

            return { id, name, username, role, created_at: createdAt };
        }

        if (query.includes('UPDATE users SET')) {
            const isAccountUpdate = query.includes('role = ?');
            const hasPassword = query.includes('password = ?');
            const id = parameters.at(-1);
            const user = this.users.find((candidate) => candidate.id === id);

            if (!user) {
                return null;
            }

            user.name = parameters[0];
            user.username = parameters[1];

            if (isAccountUpdate) {
                user.role = parameters[2];
                if (hasPassword) {
                    user.password = parameters[3];
                }
            } else if (hasPassword) {
                user.password = parameters[2];
            }

            return {
                id,
                name: user.name,
                username: user.username,
                role: user.role,
                ...(query.includes('RETURNING id, name, username, role, created_at')
                    ? { created_at: user.created_at }
                    : {}),
            };
        }

        if (query.includes('SELECT COUNT(*) AS total FROM car_transactions')
            && query.includes('NOT EXISTS')) {
            return { total: this.availableVehicleRows(query, parameters).length };
        }

        if (query.includes('SELECT COUNT(*) AS total FROM car_transactions')) {
            return { total: this.filteredTransactions(query, parameters).length };
        }

        if (query.includes('SELECT COUNT(*) AS total') && query.includes('FROM sale_notes')) {
            return { total: this.filteredSales(query, parameters).length };
        }

        if (query.includes('SUM(CASE WHEN sell_date IS NULL')) {
            const stock = this.transactions.filter((transaction) => !transaction.sell_date).length;

            return {
                total: this.transactions.length,
                stock,
                sold: this.transactions.length - stock,
            };
        }

        if (query.includes('INNER JOIN users ON users.id = api_sessions.user_id')) {
            const [tokenHash, now] = parameters;
            const session = this.sessions.get(tokenHash);

            if (!session || session.expires_at <= now) {
                return null;
            }

            const user = this.users.find((candidate) => candidate.id === session.user_id);

            return user
                ? { id: user.id, name: user.name, username: user.username, role: user.role }
                : null;
        }

        throw new Error(`Unexpected D1 query: ${query}`);
    }

    filteredTransactions(query, parameters) {
        let parameterIndex = 0;
        let transactions = [...this.transactions];

        if (query.includes('sell_date IS NULL')) {
            transactions = transactions.filter((transaction) => !transaction.sell_date);
        } else if (query.includes('sell_date IS NOT NULL')) {
            transactions = transactions.filter((transaction) => Boolean(transaction.sell_date));
        }
        if (query.includes('sell_date >= ?')) {
            const startDate = parameters[parameterIndex++];
            transactions = transactions.filter((transaction) => transaction.sell_date >= startDate);
        }
        if (query.includes('sell_date <= ?')) {
            const endDate = parameters[parameterIndex++];
            transactions = transactions.filter((transaction) => transaction.sell_date <= endDate);
        }
        if (query.includes('LIKE ?')) {
            const pattern = parameters[parameterIndex++].replaceAll('%', '').toLowerCase();
            transactions = transactions.filter((transaction) =>
                [transaction.plate_number, transaction.brand, transaction.model, String(transaction.year)]
                    .some((value) => String(value).toLowerCase().includes(pattern)));
        }

        return transactions.sort((left, right) =>
            right.created_at.localeCompare(left.created_at) || right.id - left.id);
    }

    async all(query, parameters) {
        if (query.includes('FROM showroom_expenses')) {
            let expenses = [...this.showroomExpenses];
            let parameterIndex = 0;

            if (query.includes('LOWER(title) LIKE ?')) {
                const patterns = parameters.slice(parameterIndex, parameterIndex + 3);
                parameterIndex += 3;
                expenses = expenses.filter((expense) =>
                    [expense.title, expense.recipient_name, expense.notes].some((value) =>
                        patterns.some((pattern) =>
                            String(value ?? '').toLowerCase().includes(pattern.replaceAll('%', '')))));
            }
            if (query.includes('category = ?')) {
                const category = parameters[parameterIndex++];
                expenses = expenses.filter((expense) => expense.category === category);
            }
            if (query.includes('payment_method = ?')) {
                const paymentMethod = parameters[parameterIndex++];
                expenses = expenses.filter((expense) => expense.payment_method === paymentMethod);
            }
            if (query.includes('expense_date >= ?')) {
                const startDate = parameters[parameterIndex++];
                expenses = expenses.filter((expense) => expense.expense_date >= startDate);
            }
            if (query.includes('expense_date <= ?')) {
                const endDate = parameters[parameterIndex];
                expenses = expenses.filter((expense) => expense.expense_date <= endDate);
            }

            return {
                results: expenses.sort((left, right) =>
                    right.expense_date.localeCompare(left.expense_date) || right.id - left.id),
            };
        }

        if (query.includes('FROM vehicle_photos')) {
            const photos = this.vehiclePhotos.filter((photo) =>
                query.includes('car_transaction_id IN (')
                    ? parameters.includes(photo.car_transaction_id)
                    : photo.car_transaction_id === parameters[0]);

            return { results: photos };
        }

        if (query.includes('FROM sale_payments') && query.includes('WHERE sale_note_id = ?')) {
            return {
                results: this.payments.filter((payment) => payment.sale_note_id === parameters[0])
                    .sort((left, right) =>
                        left.payment_date.localeCompare(right.payment_date) || left.id - right.id),
            };
        }

        if (query.includes('FROM users')) {
            const users = this.filteredUsers(query, parameters);
            const offset = parameters.at(-1);
            const limit = parameters.at(-2);

            return {
                results: users.slice(offset, offset + limit).map((user) => ({
                    id: user.id,
                    name: user.name,
                    username: user.username,
                    role: user.role,
                    created_at: user.created_at,
                })),
            };
        }

        if (query.includes('NOT EXISTS')) {
            const availableVehicles = this.availableVehicleRows(query, parameters);

            return query.includes('LIMIT ? OFFSET ?')
                ? { results: availableVehicles.slice(parameters.at(-1), parameters.at(-1) + parameters.at(-2)) }
                : { results: this.availableVehicles };
        }

        if (query.includes('FROM sale_notes')) {
            if (this.failSales) {
                throw new Error('D1 sales query failed');
            }

            if (!query.includes('LIMIT ? OFFSET ?')) {
                return { results: this.joinedSaleRows(query, parameters) };
            }

            const sales = this.filteredSales(query, parameters);

            return {
                results: sales.slice(parameters.at(-1), parameters.at(-1) + parameters.at(-2)),
            };
        }

        if (!query.includes('FROM car_transactions')) {
            throw new Error(`Unexpected D1 query: ${query}`);
        }

        if (query.includes('ORDER BY plate_number, id')) {
            const rows = this.availableVehicleRows(query, parameters);

            return {
                results: rows.slice(parameters.at(-1), parameters.at(-1) + parameters.at(-2)),
            };
        }

        const transactions = this.filteredTransactions(query, parameters);
        const limit = parameters.at(-2);
        const offset = parameters.at(-1);

        return {
            results: transactions.slice(offset, offset + limit),
        };
    }

    availableVehicleRows(query, parameters) {
        let rows = this.transactions.filter((transaction) =>
            !transaction.sell_date
            && !this.saleNotes.some((note) => note.car_transaction_id === transaction.id));
        let parameterIndex = 0;

        if (query.includes('plate_number LIKE ?')) {
            const pattern = parameters[parameterIndex++].replaceAll('%', '').toLowerCase();
            parameterIndex += 2;
            rows = rows.filter((row) =>
                [row.plate_number, row.brand, row.model]
                    .some((value) => String(value ?? '').toLowerCase().includes(pattern)));
        }
        if (query.includes('LOWER(transmission) = ?')) {
            const transmission = parameters[parameterIndex++].toLowerCase();
            rows = rows.filter((row) => String(row.transmission ?? '').toLowerCase() === transmission);
        }
        if (query.includes('year >= ?')) {
            rows = rows.filter((row) => Number(row.year) >= Number(parameters[parameterIndex++]));
        }
        if (query.includes('year <= ?')) {
            rows = rows.filter((row) => Number(row.year) <= Number(parameters[parameterIndex++]));
        }

        return rows.sort((left, right) =>
            String(left.plate_number ?? '').localeCompare(String(right.plate_number ?? ''))
            || left.id - right.id);
    }

    filteredSales(query, parameters) {
        let sales = [...this.sales];

        if (query.includes('sale_notes.created_by = ?')) {
            sales = sales.filter((sale) => sale.created_by === parameters[0]);
        }
        if (query.includes('END = ?')) {
            const statusIndex = query.includes('sale_notes.created_by = ?') ? 1 : 0;
            sales = sales.filter((sale) => sale.payment_status === parameters[statusIndex]);
        }

        return sales.sort((left, right) =>
            right.sell_date.localeCompare(left.sell_date) || right.sale_note_id - left.sale_note_id);
    }

    joinedSaleRows(query, parameters) {
        let rows = this.saleNotes.flatMap((note) => {
            const vehicle = this.transactions.find((transaction) =>
                transaction.id === note.car_transaction_id && transaction.sell_date);

            if (!vehicle) {
                return [];
            }

            return [{
                ...vehicle,
                ...note,
                id: note.id,
                sale_note_id: note.id,
                car_transaction_id: vehicle.id,
                payment_method: Number(vehicle.leasing_disbursement ?? 0) > 0
                    || this.payments.some((payment) =>
                        payment.sale_note_id === note.id && payment.payment_method === 'leasing')
                    ? 'leasing'
                    : 'cash',
                total_paid: Number(vehicle.down_payment ?? 0)
                    + Number(vehicle.leasing_disbursement ?? 0)
                    + Number(vehicle.settlement_amount ?? 0),
            }];
        });
        let parameterIndex = 0;

        if (query.includes('sale_notes.created_by = ?')) {
            const creatorId = parameters[parameterIndex++];
            rows = rows.filter((row) => row.created_by === creatorId);
        }
        if (query.includes('LOWER(sale_notes.nama_konsumen) LIKE ?')) {
            const searchPatterns = parameters.slice(parameterIndex, parameterIndex + 3);
            parameterIndex += 3;
            rows = rows.filter((row) => [
                row.nama_konsumen,
                row.plate_number,
                `${row.brand ?? ''} ${row.model ?? ''}`,
            ].some((value) => searchPatterns.some((pattern) =>
                String(value ?? '').toLowerCase().includes(pattern.replaceAll('%', '')))));
        }
        if (query.includes('car_transactions.sell_date >= ?')) {
            const startDate = parameters[parameterIndex++];
            rows = rows.filter((row) => row.sell_date >= startDate);
        }
        if (query.includes('car_transactions.sell_date <= ?')) {
            const endDate = parameters[parameterIndex++];
            rows = rows.filter((row) => row.sell_date <= endDate);
        }
        if (query.includes('COALESCE(car_transactions.remaining_payment, 0) <= 0')) {
            rows = rows.filter((row) => Number(row.remaining_payment ?? 0) <= 0);
        } else if (query.includes('COALESCE(car_transactions.remaining_payment, 0) > 0')) {
            rows = rows.filter((row) => Number(row.remaining_payment ?? 0) > 0);
        }
        if (query.includes("END) = 'leasing'")) {
            rows = rows.filter((row) => row.payment_method === 'leasing');
        } else if (query.includes("END) = 'cash'")) {
            rows = rows.filter((row) => row.payment_method === 'cash');
        }

        return rows.sort((left, right) =>
            right.sell_date.localeCompare(left.sell_date) || right.sale_note_id - left.sale_note_id);
    }

    filteredUsers(query, parameters) {
        let users = [...this.users];

        if (query.includes('name LIKE ?')) {
            const search = parameters[0].replaceAll('%', '').toLowerCase();
            users = users.filter((user) =>
                [user.name, user.username, user.role].some((value) =>
                    String(value).toLowerCase().includes(search)));
        }

        return users.sort((left, right) =>
            String(right.created_at ?? '').localeCompare(String(left.created_at ?? ''))
            || right.id - left.id);
    }

    async run(query, parameters) {
        if (query.includes('INSERT INTO showroom_expenses')) {
            const [
                expenseDate, category, title, amount, paymentMethod,
                recipientName, notes, createdBy, createdAt, updatedAt,
            ] = parameters;
            const id = this.nextExpenseId++;
            this.showroomExpenses.push({
                id,
                expense_date: expenseDate,
                category,
                title,
                amount,
                payment_method: paymentMethod,
                recipient_name: recipientName,
                notes,
                receipt_path: null,
                created_by: createdBy,
                created_at: createdAt,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1, last_row_id: id } };
        }

        if (query.includes('UPDATE showroom_expenses SET receipt_path = ?')) {
            const [receiptPath, updatedAt, id] = parameters;
            const expense = this.showroomExpenses.find((item) => item.id === id);
            if (expense) {
                expense.receipt_path = receiptPath;
                expense.updated_at = updatedAt;
            }

            return { success: true, meta: { changes: expense ? 1 : 0 } };
        }

        if (query.includes('UPDATE showroom_expenses') && query.includes('SET expense_date = ?')) {
            const [
                expenseDate, category, title, amount, paymentMethod,
                recipientName, notes, receiptPath, updatedAt, id,
            ] = parameters;
            const expense = this.showroomExpenses.find((item) => item.id === id);
            if (expense) {
                Object.assign(expense, {
                    expense_date: expenseDate,
                    category,
                    title,
                    amount,
                    payment_method: paymentMethod,
                    recipient_name: recipientName,
                    notes,
                    receipt_path: receiptPath,
                    updated_at: updatedAt,
                });
            }

            return { success: true, meta: { changes: expense ? 1 : 0 } };
        }

        if (query.includes('DELETE FROM showroom_expenses WHERE id = ?')) {
            const expenseId = parameters[0];
            const exists = this.showroomExpenses.some((expense) => expense.id === expenseId);
            this.showroomExpenses = this.showroomExpenses.filter((expense) => expense.id !== expenseId);

            return { success: true, meta: { changes: exists ? 1 : 0 } };
        }

        if (query.includes('INSERT INTO nota_settings')) {
            const [
                showroomName, subtitle, showroomAddress, showroomPhone, invoicePrefix,
                footerNote, signatoryName, signatoryTitle, showBuyerNik,
                showVehicleIdentifiers, showPaymentBreakdown, showPaymentStatus,
                showSignatures, accentTheme, paperSize, designConfig, updatedBy, updatedAt,
            ] = parameters;
            this.notaSettings = {
                showroom_name: showroomName,
                subtitle,
                showroom_address: showroomAddress,
                showroom_phone: showroomPhone,
                invoice_prefix: invoicePrefix,
                footer_note: footerNote,
                signatory_name: signatoryName,
                signatory_title: signatoryTitle,
                show_buyer_nik: showBuyerNik,
                show_vehicle_identifiers: showVehicleIdentifiers,
                show_payment_breakdown: showPaymentBreakdown,
                show_payment_status: showPaymentStatus,
                show_signatures: showSignatures,
                accent_theme: accentTheme,
                paper_size: paperSize,
                design_config: designConfig,
                updated_by: updatedBy,
                updated_at: updatedAt,
            };

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('DELETE FROM api_sessions WHERE expires_at <= ?')) {
            for (const [tokenHash, session] of this.sessions) {
                if (session.expires_at <= parameters[0]) {
                    this.sessions.delete(tokenHash);
                }
            }

            return { success: true };
        }

        if (query.includes('INSERT INTO api_sessions')) {
            const [tokenHash, userId, expiresAt, createdAt] = parameters;
            this.sessions.set(tokenHash, { user_id: userId, expires_at: expiresAt, created_at: createdAt });

            return { success: true };
        }

        if (query.includes('DELETE FROM api_sessions WHERE token_hash = ?')) {
            this.sessions.delete(parameters[0]);

            return { success: true };
        }

        if (query.includes('DELETE FROM users WHERE id = ?')) {
            this.users = this.users.filter((user) => user.id !== parameters[0]);

            return { success: true };
        }

        if (query.includes('UPDATE sale_notes') && query.includes('SET nama_konsumen = ?')) {
            const [name, nik, phone, address, updatedAt, id, createdBy] = parameters;
            const note = this.saleNotes.find((candidate) =>
                candidate.id === id && candidate.created_by === createdBy);

            if (!note) {
                return { success: true, meta: { changes: 0 } };
            }

            Object.assign(note, {
                nama_konsumen: name,
                buyer_nik: nik,
                buyer_phone: phone,
                buyer_address: address,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('DELETE FROM sale_notes WHERE id = ?')) {
            const [id, createdBy] = parameters;
            const note = this.saleNotes.find((candidate) =>
                candidate.id === id && candidate.created_by === createdBy);
            this.saleNotes = this.saleNotes.filter((candidate) => candidate !== note);
            this.payments = this.payments.filter((payment) => payment.sale_note_id !== id);

            return { success: true, meta: { changes: note ? 1 : 0 } };
        }

        if (query.includes('UPDATE car_transactions') && query.includes('SET sell_date = NULL')) {
            const [expenses, updatedAt, carId, saleId] = parameters;
            const transaction = this.transactions.find((candidate) =>
                candidate.id === carId
                && this.saleNotes.some((note) =>
                    note.id === saleId && note.car_transaction_id === carId));

            if (!transaction) {
                return { success: true, meta: { changes: 0 } };
            }

            Object.assign(transaction, {
                sell_date: null,
                selling_price: 0,
                down_payment: 0,
                down_payment_date: null,
                settlement_amount: 0,
                settlement_date: null,
                leasing_disbursement: 0,
                leasing_disbursement_date: null,
                remaining_payment: 0,
                planned_payment_date: null,
                intertine_expenses: expenses,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('INSERT INTO sale_payments')) {
            const [
                saleNoteId, amount, paymentMethod, paymentDate, notes,
                recordedBy, createdAt, updatedAt, carId, expectedSettlement, expectedUpdatedAt,
            ] = parameters;
            const transaction = this.transactions.find((candidate) =>
                candidate.id === carId
                && Number(candidate.settlement_amount) === expectedSettlement
                && candidate.updated_at === expectedUpdatedAt);
            const saleExists = this.saleNotes.some((note) =>
                note.id === saleNoteId && note.car_transaction_id === carId);

            if (!transaction || !saleExists) {
                return { success: true, meta: { changes: 0 } };
            }

            this.payments.push({
                id: this.payments.length + 1,
                sale_note_id: saleNoteId,
                amount,
                payment_method: paymentMethod,
                payment_date: paymentDate,
                notes,
                recorded_by: recordedBy,
                created_at: createdAt,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('DELETE FROM vehicle_photos WHERE id = ? AND car_transaction_id = ?')) {
            this.vehiclePhotos = this.vehiclePhotos.filter((photo) =>
                photo.id !== parameters[0] || photo.car_transaction_id !== parameters[1]);

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('DELETE FROM sale_payments') && query.includes('sale_note_id IN')) {
            const saleNoteIds = this.saleNotes
                .filter((note) => note.car_transaction_id === parameters[0])
                .map((note) => note.id);
            const previousLength = this.payments.length;
            this.payments = this.payments.filter((payment) => !saleNoteIds.includes(payment.sale_note_id));

            return { success: true, meta: { changes: previousLength - this.payments.length } };
        }

        if (query.includes('DELETE FROM sale_notes WHERE car_transaction_id = ?')) {
            const previousLength = this.saleNotes.length;
            this.saleNotes = this.saleNotes.filter((note) => note.car_transaction_id !== parameters[0]);

            return { success: true, meta: { changes: previousLength - this.saleNotes.length } };
        }

        if (query.includes('DELETE FROM vehicle_photos WHERE car_transaction_id = ?')) {
            const previousLength = this.vehiclePhotos.length;
            this.vehiclePhotos = this.vehiclePhotos
                .filter((photo) => photo.car_transaction_id !== parameters[0]);

            return { success: true, meta: { changes: previousLength - this.vehiclePhotos.length } };
        }

        if (query.includes('UPDATE vehicle_photos SET is_cover = 0')) {
            for (const photo of this.vehiclePhotos) {
                if (photo.car_transaction_id === parameters[1]) {
                    photo.is_cover = 0;
                    photo.updated_at = parameters[0];
                }
            }

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('UPDATE vehicle_photos SET is_cover = 1')) {
            const photo = this.vehiclePhotos.find((candidate) =>
                candidate.id === parameters[1] && candidate.car_transaction_id === parameters[2]);
            if (photo) {
                photo.is_cover = 1;
                photo.updated_at = parameters[0];
            }

            return { success: true, meta: { changes: photo ? 1 : 0 } };
        }

        if (query.includes('UPDATE car_transactions') && query.includes('SET plate_number = ?')) {
            const transaction = this.transactions.find((candidate) => candidate.id === parameters.at(-1));
            const setClause = query.match(/SET ([\s\S]+?),\s*vehicle_photo_path = \?, updated_at = \?/);

            if (!transaction || !setClause) {
                return { success: true, meta: { changes: 0 } };
            }

            const columns = setClause[1].split(',').map((column) => column.trim().split(' = ')[0]);
            columns.forEach((column, index) => {
                transaction[column] = parameters[index];
            });
            transaction.vehicle_photo_path = parameters.at(-3);
            transaction.updated_at = parameters.at(-2);

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('INSERT INTO sale_notes')) {
            const [
                carTransactionId,
                namaKonsumen,
                buyerNik,
                buyerPhone,
                buyerAddress,
                createdBy,
                createdAt,
                updatedAt,
            ] = parameters;
            const vehicle = this.transactions.find((transaction) => transaction.id === carTransactionId);
            const alreadySold = !vehicle
                || Boolean(vehicle.sell_date)
                || this.saleNotes.some((note) => note.car_transaction_id === carTransactionId)
                || this.sales.some((sale) => sale.car_transaction_id === carTransactionId);

            if (alreadySold) {
                return { success: true, meta: { changes: 0, last_row_id: 0 } };
            }

            const id = Math.max(0, ...this.saleNotes.map((note) => note.id)) + 1;
            this.saleNotes.push({
                id,
                car_transaction_id: carTransactionId,
                nama_konsumen: namaKonsumen,
                buyer_nik: buyerNik,
                buyer_phone: buyerPhone,
                buyer_address: buyerAddress,
                created_by: createdBy,
                created_at: createdAt,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1, last_row_id: id } };
        }

        if (query.includes('UPDATE car_transactions') && query.includes('SET selling_price = ?')) {
            const [
                sellingPrice,
                downPayment,
                downPaymentDate,
                settlementAmount,
                settlementDate,
                leasingDisbursement,
                leasingDisbursementDate,
                remainingPayment,
                plannedPaymentDate,
                sellDate,
                intertineExpenses,
                updatedAt,
                id,
            ] = parameters;
            const isSaleUpdate = !query.includes('sell_date IS NULL');
            const vehicle = this.transactions.find((transaction) =>
                transaction.id === id
                && (isSaleUpdate
                    || (!transaction.sell_date
                        && this.saleNotes.some((note) => note.car_transaction_id === id))));

            if (!vehicle) {
                return { success: true, meta: { changes: 0 } };
            }

            Object.assign(vehicle, {
                selling_price: sellingPrice,
                down_payment: downPayment,
                down_payment_date: downPaymentDate,
                settlement_amount: settlementAmount,
                settlement_date: settlementDate,
                leasing_disbursement: leasingDisbursement,
                leasing_disbursement_date: leasingDisbursementDate,
                remaining_payment: remainingPayment,
                planned_payment_date: plannedPaymentDate,
                sell_date: sellDate,
                intertine_expenses: intertineExpenses,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('UPDATE car_transactions') && query.includes('SET settlement_amount = ?')) {
            const [
                settlementAmount, settlementDate, remainingPayment,
                plannedPaymentDate, updatedAt, id, expectedSettlement, saleId, carId,
            ] = parameters;
            const transaction = this.transactions.find((candidate) =>
                candidate.id === id
                && Number(candidate.settlement_amount ?? 0) === expectedSettlement
                && Number(candidate.remaining_payment ?? 0) > 0
                && this.saleNotes.some((note) =>
                    note.id === saleId && note.car_transaction_id === carId));

            if (!transaction) {
                return { success: true, meta: { changes: 0 } };
            }

            Object.assign(transaction, {
                settlement_amount: settlementAmount,
                settlement_date: settlementDate,
                remaining_payment: remainingPayment,
                planned_payment_date: plannedPaymentDate,
                updated_at: updatedAt,
            });

            return { success: true, meta: { changes: 1 } };
        }

        if (query.includes('INSERT INTO car_transactions')) {
            const columnList = query.match(/\(([\s\S]+?)\)\s+VALUES/);
            assert.ok(columnList);
            const columns = columnList[1].split(',').map((column) => column.trim());
            assert.equal(columns.length, parameters.length);
            const transaction = Object.fromEntries(columns.map((column, index) => [column, parameters[index]]));
            const id = Math.max(0, ...this.transactions.map((item) => item.id ?? 0)) + 1;
            transaction.id = id;
            this.transactions.push(transaction);

            return { success: true, meta: { last_row_id: id } };
        }

        if (query.includes('INSERT INTO vehicle_photos')) {
            if (this.failVehiclePhotoInsert) {
                throw new Error('Simulated D1 vehicle photo insert failure.');
            }

            const [carTransactionId, path, isCover, position, createdAt, updatedAt] = parameters;
            const id = this.vehiclePhotos.length + 1;
            this.vehiclePhotos.push({
                id,
                car_transaction_id: carTransactionId,
                path,
                is_cover: isCover,
                position,
                created_at: createdAt,
                updated_at: updatedAt,
            });

            return { success: true, meta: { last_row_id: id } };
        }

        if (query.includes('UPDATE car_transactions SET vehicle_photo_path = ?')) {
            const [path, updatedAt, id] = parameters;
            const transaction = this.transactions.find((item) => item.id === id);

            if (transaction) {
                transaction.vehicle_photo_path = path;
                transaction.updated_at = updatedAt;
            }

            return { success: true };
        }

        if (query.includes('DELETE FROM car_transactions WHERE id = ?')) {
            const transactionExists = this.transactions.some((item) => item.id === parameters[0]);
            this.transactions = this.transactions.filter((item) => item.id !== parameters[0]);
            this.vehiclePhotos = this.vehiclePhotos.filter((item) => item.car_transaction_id !== parameters[0]);
            this.saleNotes = this.saleNotes.filter((item) => item.car_transaction_id !== parameters[0]);
            this.payments = this.payments.filter((payment) =>
                this.saleNotes.some((note) => note.id === payment.sale_note_id));

            return { success: true, meta: { changes: transactionExists ? 1 : 0 } };
        }

        throw new Error(`Unexpected D1 query: ${query}`);
    }

    async batch(statements) {
        const results = [];

        for (const statement of statements) {
            results.push(await statement.run());
        }

        return results;
    }
}

function createEnvironment(database = new MockD1()) {
    const storedPhotos = new Map();

    return {
        DB: database,
        VEHICLE_PHOTOS: {
            failPut: false,
            async put(key, value, options) {
                if (this.failPut) {
                    throw new Error('Simulated R2 photo upload failure.');
                }

                storedPhotos.set(key, { value, options });
            },
            async get(key) {
                const storedPhoto = storedPhotos.get(key);

                if (!storedPhoto) {
                    return null;
                }

                const value = storedPhoto.bytes ?? storedPhoto.value ?? new Uint8Array();

                return {
                    body: new Response(value).body,
                    httpMetadata: storedPhoto.options?.httpMetadata,
                };
            },
            async delete(key) {
                storedPhotos.delete(key);
            },
            storedPhotos,
        },
        VEHICLE_PHOTOS_PUBLIC_URL: '',
        FRONTEND_ORIGIN: 'https://sistem-toko.pages.dev',
    };
}

function jsonRequest(path, body, headers = {}) {
    const requestHeaders = { ...headers };
    const method = requestHeaders.method ?? 'POST';
    delete requestHeaders.method;

    return new Request(`https://sistem-toko-api.test${path}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Origin: 'https://sistem-toko.pages.dev',
            ...requestHeaders,
        },
        body: JSON.stringify(body),
    });
}

function vehicleFixture(id, overrides = {}) {
    return {
        id,
        plate_number: `B ${id} XY`,
        manager_name: 'Manager',
        brand: 'Toyota',
        model: 'Avanza',
        year: 2022,
        transmission: 'manual',
        color: 'Hitam',
        chassis_number: 'CHASSIS',
        engine_number: 'ENGINE',
        mileage_km: 12000,
        owner_name: 'Pemilik',
        buy_date: '2026-01-01',
        sell_date: null,
        buy_price: 100000000,
        purchase_operational: 1000000,
        other_costs: 0,
        total_repair_cost: 0,
        total_capital: 101000000,
        selling_price: 0,
        down_payment: 0,
        down_payment_date: null,
        settlement_amount: 0,
        settlement_date: null,
        leasing_disbursement: 0,
        leasing_disbursement_date: null,
        planned_payment_date: null,
        remaining_payment: 0,
        gross_profit: 0,
        showroom_fee: 0,
        total_intertine_expense: 0,
        net_profit: 0,
        setor_price: 0,
        opening_price: 120000000,
        leasing_ripan: 0,
        insurance_ripan: 0,
        booster_ripan: 0,
        seller_name: 'Seller',
        seller_nik: null,
        seller_phone: null,
        seller_address: null,
        broker_name: null,
        additional_repairs: '[]',
        tax_items: '[{"name":"Pajak","date":"2027-01-01","cost":0}]',
        intertine_expenses: '[]',
        leasing_options: '[{"name":"Leasing","disbursement":100000000}]',
        vehicle_photo_path: null,
        created_at: '2026-01-01 10:00:00',
        updated_at: '2026-01-01 10:00:00',
        ...overrides,
    };
}

function validTransactionPayload(overrides = {}) {
    return {
        plate_number: 'B 1234 XY',
        brand: 'Toyota',
        model: 'Avanza',
        year: 2022,
        transmission: 'manual',
        color: 'Hitam',
        buy_date: '2026-01-01',
        seller_name: 'Seller',
        ...overrides,
    };
}

function validSalePayload(overrides = {}) {
    return {
        nama_konsumen: 'Pembeli',
        buyer_nik: '',
        buyer_phone: '',
        buyer_address: '',
        selling_price: 'Rp 150.000.000',
        down_payment: 10000000,
        down_payment_date: '2026-01-01',
        settlement_amount: 0,
        settlement_date: '',
        leasing_disbursement: 0,
        leasing_disbursement_date: '',
        sell_date: '2026-01-01',
        planned_payment_date: '2026-02-01',
        intertine_expenses: [
            { name: 'KR Jual', cost: 100000 },
            { name: 'OP Survei', cost: 200000 },
            { name: 'Fee CMO', cost: 300000 },
        ],
        ...overrides,
    };
}

async function authenticate(database, environment, role = 'master') {
    const user = {
        id: 1,
        name: `${role} user`,
        username: `${role}-user`,
        password: await hash('correct-password', 4),
        role,
    };
    database.users.push(user);
    const response = await worker.fetch(
        jsonRequest('/api/spa/login', { username: user.username, password: 'correct-password' }),
        environment,
    );
    const payload = await response.json();

    assert.equal(response.status, 200);

    return { user, token: payload.token };
}

function saleFixture(database, { id = 1, creatorId = 1, remaining = 120000000 } = {}) {
    const vehicleId = id + 100;
    const transaction = vehicleFixture(vehicleId, {
        sell_date: '2026-01-01',
        selling_price: 150000000,
        down_payment: 10000000,
        down_payment_date: '2026-01-01',
        settlement_amount: 0,
        leasing_disbursement: 0,
        remaining_payment: remaining,
        planned_payment_date: '2026-02-01',
        intertine_expenses: JSON.stringify([
            { name: 'KR Jual', cost: 100000 },
            { name: 'Biaya lain', cost: 50000 },
        ]),
    });
    const note = {
        id,
        car_transaction_id: vehicleId,
        nama_konsumen: 'Pembeli',
        buyer_nik: null,
        buyer_phone: null,
        buyer_address: null,
        created_by: creatorId,
        created_at: '2026-01-01 10:00:00',
        updated_at: '2026-01-01 10:00:00',
    };
    database.transactions.push(transaction);
    database.saleNotes.push(note);

    return { transaction, note };
}

test('login validates Laravel bcrypt passwords and stores only a hashed session token', async () => {
    const database = new MockD1();
    const laravelPasswordHash = (await hash('correct-password', 4)).replace('$2b$', '$2y$');
    database.users.push({
        id: 7,
        name: 'Master User',
        username: 'master',
        password: laravelPasswordHash,
        role: 'master',
    });

    const response = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        createEnvironment(database),
    );
    const payload = await response.json();

    assert.equal(response.status, 200);
    assert.equal(payload.message, 'Login berhasil');
    assert.equal(payload.user.username, 'master');
    assert.equal(payload.redirect, '/app/dashboard');
    assert.equal('password' in payload.user, false);
    assert.equal(typeof payload.token, 'string');
    assert.equal(database.sessions.size, 1);
    assert.equal(database.sessions.has(payload.token), false);
});

test('login rejects invalid passwords and an empty database without issuing sessions', async () => {
    const database = new MockD1();
    database.users.push({
        id: 7,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });

    const response = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'wrong-password' }),
        createEnvironment(database),
    );
    const payload = await response.json();

    assert.equal(response.status, 401);
    assert.equal(payload.message, 'Username atau password salah');
    assert.equal(database.sessions.size, 0);

    const emptyDatabaseResponse = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'wrong-password' }),
        createEnvironment(new MockD1()),
    );

    assert.equal(emptyDatabaseResponse.status, 401);
    assert.equal((await emptyDatabaseResponse.json()).message, 'Username atau password salah');
});

test('login rejects unknown usernames without exposing whether the user exists', async () => {
    const database = new MockD1();
    database.users.push({
        id: 7,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });

    const response = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'missing', password: 'wrong-password' }),
        createEnvironment(database),
    );
    const payload = await response.json();

    assert.equal(response.status, 401);
    assert.equal(payload.message, 'Username atau password salah');
    assert.equal(database.sessions.size, 0);
});

test('dashboard endpoints require a bearer session and return filtered paginated vehicle data', async () => {
    const database = new MockD1();
    database.users.push({
        id: 8,
        name: 'Admin User',
        username: 'admin',
        password: await hash('correct-password', 4),
        role: 'admin',
    });
    database.transactions.push(
        {
            id: 1,
            plate_number: 'B 1234 AAA',
            brand: 'Toyota',
            model: 'Avanza',
            year: 2022,
            buy_date: '2026-01-01',
            sell_date: null,
            selling_price: 150000000,
            setor_price: 140000000,
            opening_price: 130000000,
            buy_price: 100000000,
            total_capital: 110000000,
            tax_items: '[{"name":"Pajak","date":"2027-01-01"}]',
            created_at: '2026-01-01 10:00:00',
        },
        {
            id: 2,
            plate_number: 'D 5678 BBB',
            brand: 'Honda',
            model: 'Brio',
            year: 2023,
            buy_date: '2026-02-01',
            sell_date: '2026-03-01',
            selling_price: 180000000,
            setor_price: 170000000,
            opening_price: 160000000,
            buy_price: 120000000,
            total_capital: 125000000,
            tax_items: '[]',
            created_at: '2026-03-01 10:00:00',
        },
    );
    database.vehiclePhotos.push({
        id: 1,
        car_transaction_id: 1,
        path: 'vehicles/1/dashboard.webp',
        is_cover: 1,
        position: 0,
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const unauthenticated = await worker.fetch(
        new Request('https://sistem-toko-api.test/api/spa/dashboard'),
        environment,
    );

    assert.equal(unauthenticated.status, 401);

    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/dashboard?view=inventory&q=Toyota&page=1',
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const payload = await response.json();
    const transactionsResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions?view=inventory&q=Toyota&page=1',
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const transactionsPayload = await transactionsResponse.json();

    assert.equal(response.status, 200);
    assert.equal(response.headers.get('Content-Type'), 'application/json');
    assert.equal(environment.VEHICLE_PHOTOS_PUBLIC_URL, '');
    assert.equal(payload.data.length, 1);
    assert.equal(payload.data[0].plate_number, 'B 1234 AAA');
    assert.equal(payload.data[0].status, 'stock');
    assert.equal(
        payload.data[0].vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/1/dashboard.webp',
    );
    assert.equal(transactionsResponse.status, 200);
    assert.equal(
        transactionsPayload.data[0].vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/1/dashboard.webp',
    );
    assert.equal(payload.data[0].tax_status.label, 'Hidup');
    assert.equal(payload.data[0].can_update, true);
    assert.equal('total_capital' in payload.data[0], false);
    assert.deepEqual(payload.meta, {
        current_page: 1,
        from: 1,
        last_page: 1,
        per_page: 10,
        to: 1,
        total: 1,
    });
    assert.deepEqual(payload.statistics, { total: 2, stock: 1, sold: 1 });
    assert.equal(payload.permissions.can_view_sensitive_data, false);

    const aliasResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions?view=sales',
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const aliasPayload = await aliasResponse.json();

    assert.equal(aliasResponse.status, 200);
    assert.equal(aliasPayload.data[0].status, 'sold');
});

test('dashboard returns empty JSON data and denies marketing users', async () => {
    const database = new MockD1();
    database.users.push(
        {
            id: 9,
            name: 'Admin User',
            username: 'admin',
            password: await hash('correct-password', 4),
            role: 'admin',
        },
        {
            id: 10,
            name: 'Marketing User',
            username: 'marketing',
            password: await hash('correct-password', 4),
            role: 'marketing',
        },
    );
    const environment = createEnvironment(database);
    const adminLogin = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin', password: 'correct-password' }),
        environment,
    );
    const { token: adminToken } = await adminLogin.json();
    const emptyResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions',
        { headers: { Authorization: `Bearer ${adminToken}` } },
    ), environment);
    const emptyPayload = await emptyResponse.json();

    assert.equal(emptyResponse.status, 200);
    assert.deepEqual(emptyPayload.data, []);
    assert.deepEqual(emptyPayload.statistics, { total: 0, stock: 0, sold: 0 });

    const marketingLogin = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'marketing', password: 'correct-password' }),
        environment,
    );
    const { token: marketingToken } = await marketingLogin.json();
    const forbiddenResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions',
        { headers: { Authorization: `Bearer ${marketingToken}` } },
    ), environment);

    assert.equal(forbiddenResponse.status, 403);
});

test('transaction creation accepts JSON and derives totals without exposing master-only amounts', async () => {
    const database = new MockD1();
    database.users.push({
        id: 21,
        name: 'Admin User',
        username: 'admin-create',
        password: await hash('correct-password', 4),
        role: 'admin',
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin-create', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/transactions', {
            plate_number: 'B 1234 AAA',
            brand: 'Toyota',
            model: 'Avanza',
            year: 2022,
            transmission: 'Automatic',
            color: 'Hitam',
            buy_date: '2026-01-01',
            buy_price: 125000000,
            purchase_operational: 1000000,
            other_costs: 500000,
            selling_price: 140000000,
            nama_penjual: 'Nama Penjual',
            tax_items: [{ name: 'Pajak', cost: 500000, estimated_cost: 600000, date: '2027-01-01' }],
            additional_repairs: [{
                name: 'Servis',
                date: '2026-01-02',
                cost: 1000000,
                payment_status: 'lunas',
            }],
            intertine_expenses: [{ name: 'KR Jual', cost: 10000 }],
            leasing_options: [{ name: 'BCA', disbursement: 100000000 }],
        }, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const payload = await response.json();

    assert.equal(response.status, 201);
    assert.equal(payload.message, 'Transaksi berhasil disimpan');
    assert.equal(payload.data.id, 1);
    assert.equal(payload.data.buy_price, 0);
    assert.equal(payload.data.total_capital, 3000000);
    assert.equal(payload.data.remaining_payment, 140000000);
    assert.equal(database.transactions.length, 1);
    assert.equal(database.transactions[0].seller_name, 'Nama Penjual');
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 0);
});

test('multipart transaction creation stores vehicle photos in R2 and records the cover in D1', async () => {
    const database = new MockD1();
    database.users.push({
        id: 22,
        name: 'Master User',
        username: 'master-create',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master-create', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const formData = new FormData();

    for (const [key, value] of Object.entries({
        plate_number: 'D 5678 BBB',
        brand: 'Honda',
        model: 'Brio',
        year: '2023',
        transmission: 'Manual',
        color: 'Putih',
        buy_date: '2026-02-01',
        buy_price: '120000000',
        nama_penjual: 'Penjual',
    })) {
        formData.append(key, value);
    }

    const uploadedPhotoBytes = [
        new Uint8Array([0xff, 0xd8, 0xff, 0xe0]),
        new Uint8Array([0x89, 0x50, 0x4e, 0x47]),
    ];
    formData.append('photos[]', new File([uploadedPhotoBytes[0]], 'mobil.jpg', {
        type: 'image/jpeg',
    }));
    formData.append('photos[]', new File([uploadedPhotoBytes[1]], 'mobil.png', {
        type: 'image/png',
    }));
    formData.append('foto_utama', 'new:1');

    const response = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/transactions', {
        method: 'POST',
        headers: {
            Origin: 'https://sistem-toko.pages.dev',
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
        body: formData,
    }), environment);
    const payload = await response.json();

    assert.equal(response.status, 201);
    assert.equal(payload.message, 'Transaksi berhasil disimpan');
    assert.equal(payload.data.vehicle_photos.length, 2);
    assert.equal(database.vehiclePhotos.length, 2);
    assert.equal(database.vehiclePhotos.every((photo) => photo.car_transaction_id === payload.data.id), true);
    assert.deepEqual(database.vehiclePhotos.map((photo) => photo.is_cover), [0, 1]);
    assert.equal(payload.data.vehicle_photos.every((photo) =>
        new URL(photo.url).origin === 'https://sistem-toko-api.test'), true);
    assert.equal(database.transactions[0].vehicle_photo_path, database.vehiclePhotos[1].path);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 2);

    const detailResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/spa/transactions/${payload.data.id}`,
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const detailPayload = await detailResponse.json();

    assert.equal(detailResponse.status, 200);
    assert.equal(detailPayload.data.vehicle_photos.length, 2);
    assert.deepEqual(detailPayload.data.vehicle_photos.map((photo) => photo.is_primary), [false, true]);
    assert.equal(detailPayload.data.vehicle_photo_url, detailPayload.data.vehicle_photos[1].url);

    for (const [index, photo] of detailPayload.data.vehicle_photos.entries()) {
        const photoUrl = photo.url;
        const photoResponse = await worker.fetch(new Request(photoUrl), environment);

        assert.equal(new URL(photoUrl).origin, 'https://sistem-toko-api.test');
        assert.equal(photoResponse.status, 200);
        assert.equal(photoResponse.headers.get('Content-Type'), index === 0 ? 'image/jpeg' : 'image/png');
        assert.deepEqual(
            new Uint8Array(await photoResponse.arrayBuffer()),
            uploadedPhotoBytes[index],
        );
    }
});

test('transaction creation returns an error and cleans up when R2 or D1 photo writes fail', async () => {
    for (const failedStage of ['R2', 'D1']) {
        const database = new MockD1();
        const environment = createEnvironment(database);
        const { token } = await authenticate(database, environment);

        environment.VEHICLE_PHOTOS.failPut = failedStage === 'R2';
        database.failVehiclePhotoInsert = failedStage === 'D1';

        const formData = new FormData();
        for (const [key, value] of Object.entries({
            plate_number: `D ${failedStage === 'R2' ? '1001' : '1002'} ERR`,
            brand: 'Honda',
            model: 'Brio',
            year: '2023',
            transmission: 'Manual',
            color: 'Putih',
            buy_date: '2026-02-01',
            buy_price: '120000000',
            nama_penjual: 'Penjual',
        })) {
            formData.append(key, value);
        }
        formData.append('photos[]', new File([new Uint8Array([0xff, 0xd8, 0xff])], 'mobil.jpg', {
            type: 'image/jpeg',
        }));

        const response = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/transactions', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
            body: formData,
        }), environment);

        assert.equal(response.status, 500);
        assert.equal(database.transactions.length, 0);
        assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 0);
    }
});

test('transaction creation rejects unsupported photo types before writing data', async () => {
    const database = new MockD1();
    database.users.push({
        id: 23,
        name: 'Master User',
        username: 'master-invalid',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master-invalid', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const formData = new FormData();
    formData.append('foto_kendaraan[]', new File(['<svg/>'], 'vehicle.svg', { type: 'image/svg+xml' }));

    const response = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/transactions', {
        method: 'POST',
        headers: {
            Origin: 'https://sistem-toko.pages.dev',
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
        body: formData,
    }), environment);
    const payload = await response.json();

    assert.equal(response.status, 422);
    assert.deepEqual(payload.errors.foto_kendaraan, ['Foto harus berformat JPG, PNG, atau WEBP.']);
    assert.equal(database.transactions.length, 0);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 0);
});

test('sales report returns a paginated JSON payload and filters payment status', async () => {
    const database = new MockD1();
    database.users.push({
        id: 11,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    database.sales.push(
        {
            sale_note_id: 31,
            car_transaction_id: 41,
            nama_konsumen: 'Buyer A',
            created_by: 11,
            sell_date: '2026-11-02',
            plate_number: 'B 1111 ABC',
            brand: 'Toyota',
            model: 'Avanza',
            year: 2022,
            selling_price: 150000000,
            remaining_payment: 0,
            payment_status: 'paid',
            total_paid: 150000000,
        },
        {
            sale_note_id: 32,
            car_transaction_id: 42,
            nama_konsumen: 'Buyer B',
            created_by: 11,
            sell_date: '2026-11-01',
            plate_number: 'D 2222 XYZ',
            brand: 'Honda',
            model: 'Brio',
            year: 2023,
            selling_price: 120000000,
            remaining_payment: 10000000,
            payment_status: 'unpaid',
            total_paid: 110000000,
        },
    );
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual?status=unpaid&page=1',
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const payload = await response.json();

    assert.equal(response.status, 200);
    assert.equal(response.headers.get('Content-Type'), 'application/json');
    assert.equal(payload.data.length, 1);
    assert.equal(payload.data[0].id, 32);
    assert.equal(payload.data[0].nama_konsumen, 'Buyer B');
    assert.equal(payload.data[0].payment_status, 'unpaid');
    assert.equal(payload.data[0].car_transaction.id, 42);
    assert.equal(payload.can_view_sensitive_data, true);
    assert.equal(payload.status, 'unpaid');
    assert.deepEqual(payload.meta, {
        current_page: 1,
        from: 1,
        last_page: 1,
        per_page: 10,
        to: 1,
        total: 1,
    });
});

test('sales report applies date, search, and payment filters and calculates summary totals', async () => {
    const database = new MockD1();
    const { transaction: paidVehicle, note: paidNote } = saleFixture(database, {
        id: 61,
        remaining: 0,
    });
    Object.assign(paidNote, {
        nama_konsumen: 'Pembeli Toyota',
        buyer_nik: '1111222233334444',
        buyer_phone: '0811111111',
    });
    Object.assign(paidVehicle, {
        sell_date: '2026-11-02',
        selling_price: 150000000,
        down_payment: 50000000,
        settlement_amount: 100000000,
        remaining_payment: 0,
        total_capital: 110000000,
        total_intertine_expense: 5000000,
        net_profit: 35000000,
    });

    const { transaction: unpaidVehicle, note: unpaidNote } = saleFixture(database, {
        id: 62,
        remaining: 80000000,
    });
    Object.assign(unpaidNote, { nama_konsumen: 'Pembeli Honda' });
    Object.assign(unpaidVehicle, {
        sell_date: '2026-11-12',
        brand: 'Honda',
        model: 'Brio',
        selling_price: 120000000,
        down_payment: 20000000,
        settlement_amount: 10000000,
        leasing_disbursement: 10000000,
        remaining_payment: 80000000,
        total_capital: 90000000,
        total_intertine_expense: 3000000,
        net_profit: 27000000,
        intertine_expenses: JSON.stringify([
            { name: 'KR Jual', cost: 100000 },
            { name: 'OP Survei', cost: 200000 },
            { name: 'Fee CMO', cost: 300000 },
        ]),
    });

    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan',
        { headers },
    ), environment);
    const payload = await response.json();
    const filteredResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan?search=Honda&start_date=2026-11-01&end_date=2026-11-30&payment_status=belum_lunas',
        { headers },
    ), environment);
    const filteredPayload = await filteredResponse.json();
    const leasingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan?payment_method=leasing',
        { headers },
    ), environment);
    const leasingPayload = await leasingResponse.json();
    const cashResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan?payment_method=cash',
        { headers },
    ), environment);
    const cashPayload = await cashResponse.json();
    const invalidFilterResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan?start_date=2026-12-01&end_date=2026-11-30',
        { headers },
    ), environment);
    const invalidPaymentMethodResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan?payment_method=unknown',
        { headers },
    ), environment);

    assert.equal(response.status, 200);
    assert.deepEqual(payload.summary, {
        total_units: 2,
        total_omzet: 270000000,
        total_paid: 190000000,
        total_receivable: 80000000,
        paid_units: 1,
        unpaid_units: 1,
        total_net_profit: 62000000,
    });
    assert.equal(payload.data.length, 2);
    assert.equal(payload.data[0].receipt_number, 'NJM/2026/000062');
    assert.equal(payload.data[1].buyer_phone, '0811111111');
    assert.equal(payload.data[0].payment_method, 'leasing');
    assert.equal(payload.data[0].car_transaction.color, 'Hitam');
    assert.equal(payload.data[0].car_transaction.buy_price, 100000000);
    assert.equal(payload.data[0].car_transaction.total_capital, 90000000);
    assert.equal(payload.data[0].car_transaction.total_intertine_expense, 3000000);
    assert.equal(payload.data[0].car_transaction.net_profit, 27000000);
    assert.deepEqual(payload.data[0].car_transaction.commissions, {
        kr_jual: 100000,
        op_survei: 200000,
        fee_cmo: 300000,
        total: 600000,
    });
    assert.equal(payload.data[0].car_transaction.down_payment, 20000000);
    assert.equal(payload.data[0].car_transaction.settlement_amount, 10000000);
    assert.equal(payload.data[0].car_transaction.leasing_disbursement, 10000000);
    assert.equal(payload.data[0].car_transaction.remaining_payment, 80000000);
    assert.equal(filteredResponse.status, 200);
    assert.equal(filteredPayload.data.length, 1);
    assert.equal(filteredPayload.data[0].nama_konsumen, 'Pembeli Honda');
    assert.equal(filteredPayload.data[0].payment_status, 'belum_lunas');
    assert.equal(leasingResponse.status, 200);
    assert.equal(leasingPayload.data.length, 1);
    assert.equal(cashResponse.status, 200);
    assert.equal(cashPayload.data.length, 1);
    assert.equal(invalidFilterResponse.status, 422);
    assert.equal(invalidPaymentMethodResponse.status, 422);
});

test('sale receipt endpoints return printable buyer, vehicle, and payment details', async () => {
    const database = new MockD1();
    const { transaction, note } = saleFixture(database, { id: 71, remaining: 80000000 });
    Object.assign(note, {
        nama_konsumen: 'Pembeli Nota',
        buyer_nik: '1234567890123456',
        buyer_phone: '08123456789',
        buyer_address: 'Jl. Cempaka No. 1',
    });
    Object.assign(transaction, {
        sell_date: '2026-11-05',
        plate_number: 'B 1717 NOT',
        brand: 'Toyota',
        model: 'Avanza',
        year: 2022,
        color: 'Hitam',
        chassis_number: 'CHASSIS-NOTA',
        engine_number: 'ENGINE-NOTA',
        selling_price: 150000000,
        down_payment: 30000000,
        settlement_amount: 20000000,
        leasing_disbursement: 20000000,
        remaining_payment: 80000000,
    });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const listResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-penjualan',
        { headers },
    ), environment);
    const listPayload = await listResponse.json();
    const detailResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-penjualan/71',
        { headers },
    ), environment);
    const detailPayload = await detailResponse.json();
    const missingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-penjualan/999',
        { headers },
    ), environment);

    assert.equal(listResponse.status, 200);
    assert.equal(listPayload.data.length, 1);
    assert.equal(listPayload.data[0].receipt_number, 'NJM/2026/000071');
    assert.equal(detailResponse.status, 200);
    assert.deepEqual(detailPayload.data.buyer, {
        name: 'Pembeli Nota',
        nik: '1234567890123456',
        address: 'Jl. Cempaka No. 1',
        phone: '08123456789',
    });
    assert.deepEqual(detailPayload.data.vehicle, {
        id: 171,
        plate_number: 'B 1717 NOT',
        brand: 'Toyota',
        model: 'Avanza',
        year: 2022,
        color: 'Hitam',
        chassis_number: 'CHASSIS-NOTA',
        engine_number: 'ENGINE-NOTA',
    });
    assert.equal(detailPayload.data.payment.selling_price, 150000000);
    assert.equal(detailPayload.data.payment.total_paid, 70000000);
    assert.equal(detailPayload.data.payment.remaining_payment, 80000000);
    assert.equal(detailPayload.data.payment.status, 'belum_lunas');
    assert.equal(missingResponse.status, 404);
});

test('nota settings load defaults, persist customization, and reject invalid values', async () => {
    const database = new MockD1();
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const defaultResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-settings',
        { headers },
    ), environment);
    const defaultPayload = await defaultResponse.json();
    const designConfig = {
        preset: 'custom',
        headerLayout: 'center',
        logoUrl: 'https://example.test/showroom-logo.png',
        logoVisible: true,
        logoSize: 'large',
        sectionOrder: ['vehicle', 'buyer', 'payment', 'notes', 'signatures'],
        dataLayout: 'table',
        sectionLabels: {
            buyer: 'PIHAK KEDUA / PEMBELI',
            vehicle: 'SPESIFIKASI UNIT',
            payment: 'DETAIL TRANSAKSI',
            notes: 'CATATAN TAMBAHAN',
            signatures: 'PENGESAHAN',
        },
        fieldLabels: {
            buyerName: 'Nama Pembeli',
            buyerNik: 'NIK',
            buyerPhone: 'Nomor Telepon',
            buyerAddress: 'Alamat Pembeli',
            vehiclePlate: 'Plat',
            vehicleModel: 'Model Kendaraan',
            vehicleYear: 'Tahun',
            vehicleColor: 'Warna',
            vehicleChassis: 'Nomor Rangka',
            vehicleEngine: 'Nomor Mesin',
            sellingPrice: 'Harga Transaksi',
            downPayment: 'Uang Muka',
            settlement: 'Pelunasan',
            leasing: 'Pencairan Leasing',
            totalPaid: 'Jumlah Dibayar',
            remainingPayment: 'Sisa Tagihan',
        },
        customRows: [{ label: 'Kelengkapan', value: 'BPKB, STNK, faktur, dan kunci serep' }],
        fontFamily: 'serif',
        accentColor: '#7c2d12',
        headerBackground: '#fef2f2',
        borderStyle: 'double',
        spacing: 'compact',
        paperSize: 'f4',
        signatureColumns: 3,
    };
    const customSettings = {
        showroomName: 'Cempaka Jaya Mobilindo Utama',
        subtitle: 'Nota transaksi resmi',
        showroomAddress: 'Jl. Cempaka No. 10',
        showroomPhone: '08123456789',
        invoicePrefix: 'CJM',
        footerNote: 'Kendaraan telah diperiksa dan diterima.',
        signatoryName: 'Budi Santoso',
        signatoryTitle: 'Manajer',
        showBuyerNik: false,
        showVehicleIdentifiers: true,
        showPaymentBreakdown: false,
        showPaymentStatus: true,
        showSignatures: false,
        accentTheme: 'navy',
        paperSize: 'a5-landscape',
        design_config: designConfig,
    };
    const saveResponse = await worker.fetch(jsonRequest(
        '/api/spa/nota-settings',
        customSettings,
        { ...headers, method: 'PUT' },
    ), environment);
    const savePayload = await saveResponse.json();
    const reloadResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-settings',
        { headers },
    ), environment);
    const reloadPayload = await reloadResponse.json();
    const invalidResponse = await worker.fetch(jsonRequest(
        '/api/spa/nota-settings',
        { invoicePrefix: 'CJM/2026' },
        { ...headers, method: 'PUT' },
    ), environment);
    const invalidDesignResponse = await worker.fetch(jsonRequest(
        '/api/spa/nota-settings',
        { design_config: { ...designConfig, accentColor: 'url(javascript:alert(1))' } },
        { ...headers, method: 'PUT' },
    ), environment);

    assert.equal(defaultResponse.status, 200);
    assert.deepEqual(defaultPayload.data, {
        showroomName: 'Cempaka Jaya Mobilindo',
        subtitle: 'Bukti transaksi penjualan kendaraan',
        showroomAddress: '',
        showroomPhone: '',
        invoicePrefix: 'NJM',
        footerNote: '',
        signatoryName: 'Pihak Showroom',
        signatoryTitle: '',
        showBuyerNik: true,
        showVehicleIdentifiers: true,
        showPaymentBreakdown: true,
        showPaymentStatus: true,
        showSignatures: true,
        accentTheme: 'emerald',
        paperSize: 'a4',
        design_config: {
            preset: 'modern-clean',
            headerLayout: 'split',
            logoUrl: '',
            logoVisible: false,
            logoSize: 'medium',
            sectionOrder: ['buyer', 'vehicle', 'payment', 'notes', 'signatures'],
            dataLayout: 'grid',
            sectionLabels: {
                buyer: 'Data Pembeli',
                vehicle: 'Identitas Kendaraan',
                payment: 'Rincian Pembayaran',
                notes: 'Catatan',
                signatures: 'Tanda Tangan',
            },
            fieldLabels: {
                buyerName: 'Nama',
                buyerNik: 'NIK',
                buyerPhone: 'No. HP',
                buyerAddress: 'Alamat',
                vehiclePlate: 'Plat Nomor',
                vehicleModel: 'Merek / Model',
                vehicleYear: 'Tahun',
                vehicleColor: 'Warna',
                vehicleChassis: 'Nomor Rangka',
                vehicleEngine: 'Nomor Mesin',
                sellingPrice: 'Harga Jual',
                downPayment: 'DP',
                settlement: 'Pelunasan',
                leasing: 'Pencairan Leasing',
                totalPaid: 'Total Dibayar',
                remainingPayment: 'Sisa Pembayaran',
            },
            customRows: [],
            fontFamily: 'sans',
            accentColor: '#047857',
            headerBackground: '#ecfdf5',
            borderStyle: 'thin',
            spacing: 'normal',
            paperSize: 'a4',
            signatureColumns: 2,
        },
    });
    assert.equal(saveResponse.status, 200);
    assert.deepEqual(savePayload.data, customSettings);
    assert.equal(database.notaSettings.updated_by, 1);
    assert.equal(reloadResponse.status, 200);
    assert.deepEqual(reloadPayload.data, customSettings);
    assert.equal(invalidResponse.status, 422);
    assert.equal(invalidDesignResponse.status, 422);
});

test('sales report and receipt queries restrict Admin users to their own sales', async () => {
    const database = new MockD1();
    saleFixture(database, { id: 81, creatorId: 1 });
    saleFixture(database, { id: 82, creatorId: 2 });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment, 'admin');
    const headers = { Authorization: `Bearer ${token}` };
    const reportResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/laporan-penjualan',
        { headers },
    ), environment);
    const reportPayload = await reportResponse.json();
    const receiptsResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-penjualan',
        { headers },
    ), environment);
    const receiptsPayload = await receiptsResponse.json();
    const otherReceiptResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/nota-penjualan/82',
        { headers },
    ), environment);

    assert.equal(reportResponse.status, 200);
    assert.equal(reportPayload.data.length, 1);
    assert.equal(reportPayload.data[0].id, 81);
    assert.equal(reportPayload.can_view_sensitive_data, false);
    assert.equal(reportPayload.summary.total_net_profit, null);
    assert.equal(receiptsResponse.status, 200);
    assert.deepEqual(receiptsPayload.data.map((receipt) => receipt.id), [81]);
    assert.equal(otherReceiptResponse.status, 404);
});

test('sales report returns empty JSON and sale form returns available vehicles', async () => {
    const database = new MockD1();
    database.users.push({
        id: 12,
        name: 'Admin User',
        username: 'admin',
        password: await hash('correct-password', 4),
        role: 'admin',
    });
    database.availableVehicles.push({
        id: 50,
        plate_number: 'F 5555 DEF',
        brand: 'Suzuki',
        model: 'Ertiga',
        year: 2021,
        transmission: 'Automatic',
        color: 'Putih',
        chassis_number: 'CHASSIS-50',
        engine_number: 'ENGINE-50',
        mileage_km: 42000,
        owner_name: 'STNK Owner',
        manager_name: 'Unit Manager',
        buy_date: '2025-12-10',
        seller_name: 'Nama Penjual Asal',
        buy_price: 95000000,
        total_capital: 101000000,
        vehicle_photo_path: 'vehicles/50/cover.webp',
        additional_repairs: JSON.stringify([{ name: 'Servis', cost: 1000000 }]),
        tax_items: JSON.stringify([{ name: 'Pajak', cost: 500000 }]),
        intertine_expenses: '[]',
        leasing_options: '[]',
    });
    database.vehiclePhotos.push(
        {
            id: 501,
            car_transaction_id: 50,
            path: 'vehicles/50/front.webp',
            is_cover: 0,
            position: 0,
        },
        {
            id: 502,
            car_transaction_id: 50,
            path: 'vehicles/50/cover.webp',
            is_cover: 1,
            position: 1,
        },
    );
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const headers = { Authorization: `Bearer ${token}` };
    const emptyResponse = await worker.fetch(
        new Request('https://sistem-toko-api.test/api/spa/jual', { headers }),
        environment,
    );
    const emptyPayload = await emptyResponse.json();
    const formResponse = await worker.fetch(
        new Request('https://sistem-toko-api.test/api/spa/jual/create?vehicle=50', { headers }),
        environment,
    );
    const formPayload = await formResponse.json();

    assert.equal(emptyResponse.status, 200);
    assert.deepEqual(emptyPayload.data, []);
    assert.equal(emptyPayload.meta.total, 0);
    assert.equal(formResponse.status, 200);
    assert.equal(formPayload.data.has_available_vehicles, true);
    assert.equal(formPayload.data.selected_vehicle.id, 50);
    assert.equal(formPayload.data.can_view_sensitive_data, false);
    assert.equal(formPayload.data.vehicles[0].transmission, 'Automatic');
    assert.equal(formPayload.data.vehicles[0].color, 'Putih');
    assert.equal(formPayload.data.vehicles[0].chassis_number, 'CHASSIS-50');
    assert.equal(formPayload.data.vehicles[0].engine_number, 'ENGINE-50');
    assert.equal(formPayload.data.vehicles[0].mileage, 42000);
    assert.equal(formPayload.data.vehicles[0].stnk_name, 'STNK Owner');
    assert.equal(formPayload.data.vehicles[0].handler_name, 'Unit Manager');
    assert.equal(formPayload.data.vehicles[0].person_in_charge, 'Unit Manager');
    assert.equal(formPayload.data.vehicles[0].purchase_date, '2025-12-10');
    assert.equal(formPayload.data.vehicles[0].seller_name, 'Nama Penjual Asal');
    assert.equal(
        formPayload.data.vehicles[0].vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/50/cover.webp',
    );
    assert.deepEqual(
        formPayload.data.vehicles[0].vehicle_photos.map((photo) => photo.url),
        [
            'https://sistem-toko-api.test/api/vehicle-photos/vehicles/50/front.webp',
            'https://sistem-toko-api.test/api/vehicle-photos/vehicles/50/cover.webp',
        ],
    );
    assert.equal(formPayload.data.selected_vehicle.vehicle_photos.length, 2);
    assert.equal('purchase_price' in formPayload.data.vehicles[0], false);
    assert.equal('total_cost' in formPayload.data.vehicles[0], false);

    database.users.splice(0, database.users.length);
    const { token: masterToken } = await authenticate(database, environment, 'master');
    const masterFormResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual/create',
        { headers: { Authorization: `Bearer ${masterToken}` } },
    ), environment);
    const masterFormPayload = await masterFormResponse.json();

    assert.equal(masterFormResponse.status, 200);
    assert.equal(masterFormPayload.data.can_view_sensitive_data, true);
    assert.equal(masterFormPayload.data.vehicles[0].purchase_price, 95000000);
    assert.equal(masterFormPayload.data.vehicles[0].total_cost, 101000000);
});

test('sale creation stores buyer data and marks the selected vehicle as sold', async () => {
    const database = new MockD1();
    database.users.push({
        id: 24,
        name: 'Admin User',
        username: 'admin-sale',
        password: await hash('correct-password', 4),
        role: 'admin',
    });
    database.transactions.push({
        id: 50,
        sell_date: null,
        intertine_expenses: JSON.stringify([{ name: 'OP Beli', cost: 250000 }]),
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin-sale', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/jual', {
            car_transaction_id: 50,
            nama_konsumen: 'Nama Pembeli',
            buyer_nik: '1234567890123456',
            buyer_phone: '08123456789',
            buyer_address: 'Jl. Merdeka',
            selling_price: 'Rp 150.000.000',
            down_payment: 'Rp 25.000.000',
            down_payment_date: '2026-02-01',
            settlement_amount: 'Rp 0',
            settlement_date: '',
            leasing_disbursement: 'Rp 20.000.000',
            leasing_disbursement_date: '2026-02-03',
            sell_date: '2026-02-01',
            planned_payment_date: '2026-03-01',
            intertine_expenses: [
                { name: 'KR Jual', cost: 500000 },
                { name: 'OP Survei', cost: 250000 },
                { name: 'Fee CMO', cost: 0 },
            ],
        }, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const payload = await response.json();
    const savedVehicle = database.transactions[0];
    const expenses = JSON.parse(savedVehicle.intertine_expenses);

    assert.equal(response.status, 201);
    assert.equal(payload.message, 'Penjualan berhasil disimpan');
    assert.equal(payload.data.id, 1);
    assert.equal(payload.data.car_transaction_id, 50);
    assert.equal(database.saleNotes[0].nama_konsumen, 'Nama Pembeli');
    assert.equal(database.saleNotes[0].created_by, 24);
    assert.equal(savedVehicle.sell_date, '2026-02-01');
    assert.equal(savedVehicle.remaining_payment, 105000000);
    assert.equal(savedVehicle.settlement_date, null);
    assert.equal(savedVehicle.planned_payment_date, '2026-03-01');
    assert.equal(expenses[0].name, 'OP Beli');
    assert.equal(expenses.at(-1).name, 'Fee CMO');
});

test('sale creation returns actionable validation errors without changing the vehicle', async () => {
    const database = new MockD1();
    database.users.push({
        id: 25,
        name: 'Master User',
        username: 'master-sale-invalid',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    database.transactions.push({ id: 51, sell_date: null, intertine_expenses: '[]' });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master-sale-invalid', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/jual', {
            car_transaction_id: 51,
            nama_konsumen: '',
            selling_price: 100000000,
            down_payment: 10000000,
            settlement_amount: 0,
            leasing_disbursement: 0,
            sell_date: '',
        }, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const payload = await response.json();

    assert.equal(response.status, 422);
    assert.ok(payload.errors.nama_konsumen);
    assert.ok(payload.errors.sell_date);
    assert.ok(payload.errors.down_payment_date);
    assert.ok(payload.errors.planned_payment_date);
    assert.equal(database.saleNotes.length, 0);
    assert.equal(database.transactions[0].sell_date, null);
});

test('sale creation returns the D1 write error for diagnosis', async () => {
    const database = new MockD1();
    database.users.push({
        id: 26,
        name: 'Admin User',
        username: 'admin-sale-d1-error',
        password: await hash('correct-password', 4),
        role: 'admin',
    });
    database.transactions.push({ id: 52, sell_date: null, intertine_expenses: '[]' });
    database.batch = async () => {
        throw new Error('D1 sales write failed');
    };
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin-sale-d1-error', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/jual', {
            car_transaction_id: 52,
            nama_konsumen: 'Nama Pembeli',
            selling_price: 'Rp 150.000.000',
            down_payment: '',
            down_payment_date: '',
            settlement_amount: 0,
            settlement_date: '',
            leasing_disbursement: 0,
            leasing_disbursement_date: '',
            sell_date: '2026-11-11',
            planned_payment_date: '2026-12-01',
        }, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const payload = await response.json();

    assert.equal(response.status, 500);
    assert.equal(payload.message, 'Gagal menyimpan transaksi D1: D1 sales write failed');
});

test('sales report returns JSON 500 when a D1 query fails', async () => {
    const database = new MockD1();
    database.users.push({
        id: 13,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    database.failSales = true;
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual',
        { headers: { Authorization: `Bearer ${token}` } },
    ), environment);
    const payload = await response.json();

    assert.equal(response.status, 500);
    assert.equal(response.headers.get('Content-Type'), 'application/json');
    assert.equal(payload.message, 'Laporan penjualan tidak dapat dimuat saat ini.');
});

test('user management is master-only and creates safe Admin or Marketing accounts', async () => {
    const database = new MockD1();
    database.users.push(
        {
            id: 1,
            name: 'Master User',
            username: 'master',
            password: await hash('correct-password', 4),
            role: 'master',
            created_at: '2026-01-01 10:00:00',
        },
        {
            id: 2,
            name: 'Admin User',
            username: 'admin',
            password: await hash('correct-password', 4),
            role: 'admin',
            created_at: '2026-01-02 10:00:00',
        },
    );
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const headers = { Authorization: `Bearer ${token}` };
    const listResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/users?q=admin&page=1',
        { headers },
    ), environment);
    const listPayload = await listResponse.json();

    assert.equal(listResponse.status, 200);
    assert.equal(listPayload.data.length, 1);
    assert.equal(listPayload.data[0].username, 'admin');
    assert.equal('password' in listPayload.data[0], false);
    assert.deepEqual(listPayload.summary, { master_count: 1 });
    assert.equal(listPayload.meta.total, 1);

    const createResponse = await worker.fetch(
        jsonRequest('/api/spa/users', {
            name: 'Marketing User',
            username: 'marketing',
            password: 'a-secure-password',
            role: 'marketing',
        }, headers),
        environment,
    );
    const createPayload = await createResponse.json();
    const createdUser = database.users.find((user) => user.username === 'marketing');

    assert.equal(createResponse.status, 201);
    assert.equal(createPayload.data.role, 'marketing');
    assert.equal('password' in createPayload.data, false);
    assert.equal(await compare('a-secure-password', createdUser.password), true);

    const masterCreationResponse = await worker.fetch(
        jsonRequest('/api/spa/users', {
            name: 'Another Master',
            username: 'another-master',
            password: 'a-secure-password',
            role: 'master',
        }, headers),
        environment,
    );

    assert.equal(masterCreationResponse.status, 422);
    assert.equal(database.users.some((user) => user.username === 'another-master'), false);

    const adminLogin = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'admin', password: 'correct-password' }),
        environment,
    );
    const { token: adminToken } = await adminLogin.json();
    const forbiddenResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/users',
        { headers: { Authorization: `Bearer ${adminToken}` } },
    ), environment);

    assert.equal(forbiddenResponse.status, 403);
});

test('user update works while Master accounts cannot be changed or deleted', async () => {
    const database = new MockD1();
    database.users.push(
        {
            id: 1,
            name: 'Master User',
            username: 'master',
            password: await hash('correct-password', 4),
            role: 'master',
            created_at: '2026-01-01 10:00:00',
        },
        {
            id: 2,
            name: 'Admin User',
            username: 'admin',
            password: await hash('correct-password', 4),
            role: 'admin',
            created_at: '2026-01-02 10:00:00',
        },
    );
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const headers = { Authorization: `Bearer ${token}` };
    const updateResponse = await worker.fetch(
        jsonRequest('/api/spa/users/2', {
            name: 'Updated Admin',
            username: 'admin-updated',
            role: 'admin',
            password: 'updated-secure-password',
        }, { ...headers, method: 'PUT' }),
        environment,
    );
    const updatePayload = await updateResponse.json();

    assert.equal(updateResponse.status, 200);
    assert.equal(updatePayload.data.name, 'Updated Admin');
    assert.equal(database.users.find((user) => user.id === 2).username, 'admin-updated');
    assert.equal(await compare('updated-secure-password', database.users.find((user) => user.id === 2).password), true);

    const updateMasterResponse = await worker.fetch(
        jsonRequest('/api/spa/users/1', {
            name: 'Changed Master',
            username: 'master',
            role: 'admin',
        }, { ...headers, method: 'PUT' }),
        environment,
    );
    const deleteMasterResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/users/1',
        { method: 'DELETE', headers },
    ), environment);

    assert.equal(updateMasterResponse.status, 403);
    assert.equal(deleteMasterResponse.status, 403);
    assert.equal(database.users.some((user) => user.id === 1), true);

    const deleteUserResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/users/2',
        { method: 'DELETE', headers },
    ), environment);

    assert.equal(deleteUserResponse.status, 200);
    assert.equal(database.users.some((user) => user.id === 2), false);
});

test('profile updates require current password when changing password and protect account data', async () => {
    const database = new MockD1();
    database.users.push({
        id: 1,
        name: 'Marketing User',
        username: 'marketing',
        password: await hash('correct-password', 4),
        role: 'marketing',
        created_at: '2026-01-01 10:00:00',
    });
    const environment = createEnvironment(database);
    const login = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'marketing', password: 'correct-password' }),
        environment,
    );
    const { token } = await login.json();
    const headers = { Authorization: `Bearer ${token}` };
    const profileResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/profile',
        { headers },
    ), environment);
    const profilePayload = await profileResponse.json();

    assert.equal(profileResponse.status, 200);
    assert.equal(profilePayload.data.username, 'marketing');
    assert.equal('password' in profilePayload.data, false);

    const rejectedPasswordResponse = await worker.fetch(
        jsonRequest('/api/spa/profile', {
            name: 'Marketing User',
            username: 'marketing',
            current_password: 'wrong-password',
            password: 'new-secure-password',
            password_confirmation: 'new-secure-password',
        }, { ...headers, method: 'PUT' }),
        environment,
    );
    const rejectedPayload = await rejectedPasswordResponse.json();

    assert.equal(rejectedPasswordResponse.status, 422);
    assert.equal(rejectedPayload.errors.current_password[0], 'Kata sandi saat ini salah.');
    assert.equal(await compare('correct-password', database.users[0].password), true);

    const updateResponse = await worker.fetch(
        jsonRequest('/api/spa/profile', {
            name: 'Updated Marketing',
            username: 'marketing-updated',
            current_password: 'correct-password',
            password: 'new-secure-password',
            password_confirmation: 'new-secure-password',
        }, { ...headers, method: 'PUT' }),
        environment,
    );
    const updatePayload = await updateResponse.json();

    assert.equal(updateResponse.status, 200);
    assert.equal(updatePayload.data.name, 'Updated Marketing');
    assert.equal(updatePayload.data.username, 'marketing-updated');
    assert.equal('password' in updatePayload.data, false);
    assert.equal(await compare('new-secure-password', database.users[0].password), true);
});

test('public Master setup endpoints are unavailable and do not create users', async () => {
    const database = new MockD1();
    const postResponse = await worker.fetch(
        jsonRequest('/api/spa/setup-master', {
            name: 'Master User',
            username: 'master',
            password: 'a-secure-password',
            password_confirmation: 'a-secure-password',
        }),
        createEnvironment(database),
    );
    const getResponse = await worker.fetch(
        new Request('https://sistem-toko-api.test/api/spa/setup-master'),
        createEnvironment(database),
    );

    assert.equal(postResponse.status, 404);
    assert.equal(getResponse.status, 404);
    assert.equal(database.users.length, 0);
    assert.equal(database.sessions.size, 0);
});

test('removed sales note SPA endpoints return JSON 404 responses', async () => {
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/sale-notes',
    ), createEnvironment());

    assert.equal(response.status, 404);
    assert.equal(response.headers.get('Content-Type'), 'application/json');
    assert.equal((await response.json()).message, 'Endpoint API tidak ditemukan.');
});

test('showroom expenses support authenticated creation, filters, summary, receipt replacement, and deletion', async () => {
    const database = new MockD1();
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const unauthorized = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/pengeluaran',
    ), environment);
    const invalidCreate = await worker.fetch(
        jsonRequest('/api/spa/pengeluaran', {}),
        environment,
    );
    const invalidCreateWithToken = await worker.fetch(
        jsonRequest('/api/spa/pengeluaran', {}, headers),
        environment,
    );

    assert.equal(unauthorized.status, 401);
    assert.equal(invalidCreate.status, 401);
    assert.equal(invalidCreateWithToken.status, 422);

    const expenseDateParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const expenseDate = `${expenseDateParts.find((part) => part.type === 'year').value}-${expenseDateParts.find((part) => part.type === 'month').value}-${expenseDateParts.find((part) => part.type === 'day').value}`;
    const firstCreate = await worker.fetch(jsonRequest('/api/spa/pengeluaran', {
        expense_date: expenseDate,
        category: 'Operasional Kantor',
        title: 'Tagihan air',
        amount: 250000,
        payment_method: 'Tunai',
        recipient_name: 'Vendor Air',
        notes: 'Pembayaran bulanan',
    }, headers), environment);
    const firstPayload = await firstCreate.json();

    assert.equal(firstCreate.status, 201);
    assert.equal(firstPayload.data.amount, 250000);
    assert.equal(firstPayload.data.created_by, 1);
    assert.equal(firstPayload.data.receipt_url, null);

    const receiptForm = new FormData();
    for (const [key, value] of Object.entries({
        expense_date: expenseDate,
        category: 'Listrik, Air & Internet',
        title: 'Tagihan internet',
        amount: '150000',
        payment_method: 'Transfer Bank',
        recipient_name: 'Internet Vendor',
        notes: 'Tagihan kantor',
    })) {
        receiptForm.append(key, value);
    }
    receiptForm.append('receipt', new File([new Uint8Array([1, 2, 3])], 'receipt.png', {
        type: 'image/png',
    }));
    const receiptCreate = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/pengeluaran',
        { method: 'POST', headers, body: receiptForm },
    ), environment);
    const receiptPayload = await receiptCreate.json();
    const originalReceiptPath = database.showroomExpenses.find(
        (expense) => expense.id === receiptPayload.data.id,
    ).receipt_path;

    assert.equal(receiptCreate.status, 201);
    assert.match(receiptPayload.data.receipt_url, /\/api\/vehicle-photos\/expenses\//);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.has(originalReceiptPath), true);

    const receiptResponse = await worker.fetch(new Request(receiptPayload.data.receipt_url), environment);
    assert.equal(receiptResponse.status, 200);
    assert.equal(receiptResponse.headers.get('Content-Type'), 'image/png');
    assert.deepEqual([...new Uint8Array(await receiptResponse.arrayBuffer())], [1, 2, 3]);

    const filteredResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/pengeluaran?search=vendor&category=Operasional%20Kantor',
        { headers },
    ), environment);
    const filteredPayload = await filteredResponse.json();
    assert.equal(filteredResponse.status, 200);
    assert.equal(filteredPayload.data.length, 1);
    assert.equal(filteredPayload.summary.total_amount, 250000);
    assert.equal(filteredPayload.summary.this_month_amount, 400000);
    assert.equal(filteredPayload.summary.top_category, 'Operasional Kantor');

    const dateAndPaymentFilterResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/spa/pengeluaran?payment_method=Transfer%20Bank&start_date=${expenseDate}&end_date=${expenseDate}`,
        { headers },
    ), environment);
    const dateAndPaymentPayload = await dateAndPaymentFilterResponse.json();
    assert.equal(dateAndPaymentFilterResponse.status, 200);
    assert.equal(dateAndPaymentPayload.data.length, 1);
    assert.equal(dateAndPaymentPayload.data[0].title, 'Tagihan internet');

    const listResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/pengeluaran',
        { headers },
    ), environment);
    const listPayload = await listResponse.json();
    assert.equal(listPayload.data.length, 2);
    assert.equal(listPayload.summary.total_amount, 400000);
    assert.equal(listPayload.summary.this_month_amount, 400000);
    assert.equal(listPayload.summary.transaction_count, 2);
    assert.equal(listPayload.summary.top_category, 'Operasional Kantor');

    const replacementForm = new FormData();
    for (const [key, value] of Object.entries({
        expense_date: expenseDate,
        category: 'Listrik, Air & Internet',
        title: 'Internet showroom',
        amount: '200.000',
        payment_method: 'Tunai',
        recipient_name: 'Internet Vendor Baru',
        notes: 'Sudah diperbarui',
    })) {
        replacementForm.append(key, value);
    }
    replacementForm.append('receipt', new File([new Uint8Array([4, 5])], 'replacement.jpg', {
        type: 'image/jpeg',
    }));
    const updateResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/spa/pengeluaran/${receiptPayload.data.id}`,
        { method: 'PUT', headers, body: replacementForm },
    ), environment);
    const updatePayload = await updateResponse.json();
    const updatedReceiptPath = database.showroomExpenses.find(
        (expense) => expense.id === receiptPayload.data.id,
    ).receipt_path;

    assert.equal(updateResponse.status, 200);
    assert.equal(updatePayload.data.amount, 200000);
    assert.equal(updatePayload.data.title, 'Internet showroom');
    assert.notEqual(updatedReceiptPath, originalReceiptPath);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.has(originalReceiptPath), false);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.has(updatedReceiptPath), true);

    const postUpdateResponse = await worker.fetch(jsonRequest(
        `/api/spa/pengeluaran/${receiptPayload.data.id}`,
        { title: 'Internet showroom dikoreksi', amount: 210000 },
        { ...headers, method: 'POST' },
    ), environment);
    const postUpdatePayload = await postUpdateResponse.json();
    assert.equal(postUpdateResponse.status, 200);
    assert.equal(postUpdatePayload.data.title, 'Internet showroom dikoreksi');
    assert.equal(postUpdatePayload.data.amount, 210000);

    const deleteReceiptResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/spa/pengeluaran/${receiptPayload.data.id}`,
        { method: 'DELETE', headers },
    ), environment);
    assert.equal(deleteReceiptResponse.status, 200);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.has(updatedReceiptPath), false);

    const deleteFirstResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/spa/pengeluaran/${firstPayload.data.id}`,
        { method: 'DELETE', headers },
    ), environment);
    assert.equal(deleteFirstResponse.status, 200);
    assert.deepEqual(database.showroomExpenses, []);
});

test('CORS permits deployed previews and local development preflights with credentials', async () => {
    const environment = createEnvironment();
    const origins = [
        'https://sistem-toko.pages.dev',
        'https://0a2bd544.sistem-toko.pages.dev',
        'https://preview-build-123.sistem-toko.pages.dev',
        'http://localhost:5173',
        'http://localhost:8000',
    ];

    for (const origin of origins) {
        const response = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/login', {
            method: 'OPTIONS',
            headers: {
                Origin: origin,
                'Access-Control-Request-Method': 'POST',
                'Access-Control-Request-Headers': 'authorization,content-type,x-csrf-token',
            },
        }), environment);

        assert.equal(response.status, 204);
        assert.equal(response.headers.get('Access-Control-Allow-Origin'), origin);
        assert.equal(response.headers.get('Access-Control-Allow-Credentials'), 'true');
        const allowedMethods = response.headers.get('Access-Control-Allow-Methods');
        const allowedHeaders = response.headers.get('Access-Control-Allow-Headers');
        assert.match(allowedMethods, /GET/);
        assert.match(allowedMethods, /POST/);
        assert.match(allowedMethods, /PUT/);
        assert.match(allowedMethods, /DELETE/);
        assert.match(allowedMethods, /OPTIONS/);
        assert.match(allowedHeaders, /Accept/i);
        assert.match(allowedHeaders, /Authorization/i);
        assert.match(allowedHeaders, /Content-Type/i);
        assert.match(allowedHeaders, /X-Requested-With/i);
        assert.match(allowedHeaders, /X-CSRF-TOKEN/i);
    }

    const denied = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/login', {
        method: 'OPTIONS',
        headers: {
            Origin: 'https://sistem-toko.pages.dev.attacker.example',
            'Access-Control-Request-Method': 'POST',
        },
    }), environment);

    assert.equal(denied.headers.get('Access-Control-Allow-Origin'), null);
});

test('logout revokes the bearer session', async () => {
    const database = new MockD1();
    database.users.push({
        id: 7,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    const environment = createEnvironment(database);
    const loginResponse = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await loginResponse.json();
    const logoutResponse = await worker.fetch(
        jsonRequest('/api/spa/logout', {}, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const sessionResponse = await worker.fetch(new Request('https://sistem-toko-api.test/api/spa/me', {
        headers: { Authorization: `Bearer ${token}` },
    }), environment);

    assert.equal(logoutResponse.status, 204);
    assert.equal(sessionResponse.status, 401);
});

test('all registered protected routes return JSON 401 without or with an invalid bearer token', async () => {
    const database = new MockD1();
    const environment = createEnvironment(database);
    const requests = [
        new Request('https://sistem-toko-api.test/api/spa/dashboard'),
        new Request('https://sistem-toko-api.test/api/spa/transactions'),
        jsonRequest('/api/spa/transactions', {}),
        new Request('https://sistem-toko-api.test/api/spa/transactions/1'),
        new Request('https://sistem-toko-api.test/api/spa/transactions/1/edit'),
        jsonRequest('/api/spa/transactions/1', {}, { method: 'POST' }),
        jsonRequest('/api/spa/transactions/1', {}, { method: 'PUT' }),
        new Request('https://sistem-toko-api.test/api/spa/transactions/1', { method: 'DELETE' }),
        new Request('https://sistem-toko-api.test/api/spa/jual'),
        new Request('https://sistem-toko-api.test/api/spa/jual/create'),
        jsonRequest('/api/spa/jual', {}),
        new Request('https://sistem-toko-api.test/api/spa/jual/1'),
        new Request('https://sistem-toko-api.test/api/spa/laporan-penjualan'),
        new Request('https://sistem-toko-api.test/api/spa/nota-penjualan'),
        new Request('https://sistem-toko-api.test/api/spa/nota-penjualan/1'),
        new Request('https://sistem-toko-api.test/api/spa/nota-settings'),
        jsonRequest('/api/spa/nota-settings', {}, { method: 'PUT' }),
        jsonRequest('/api/spa/jual/1', {}, { method: 'PUT' }),
        new Request('https://sistem-toko-api.test/api/spa/jual/1', { method: 'DELETE' }),
        jsonRequest('/api/spa/jual/1/payments', {}),
        new Request('https://sistem-toko-api.test/api/spa/pengeluaran'),
        jsonRequest('/api/spa/pengeluaran', {}),
        jsonRequest('/api/spa/pengeluaran/1', {}, { method: 'PUT' }),
        jsonRequest('/api/spa/pengeluaran/1', {}, { method: 'POST' }),
        new Request('https://sistem-toko-api.test/api/spa/pengeluaran/1', { method: 'DELETE' }),
        new Request('https://sistem-toko-api.test/api/spa/marketing/vehicles'),
        new Request('https://sistem-toko-api.test/api/spa/marketing/vehicles/1'),
        new Request('https://sistem-toko-api.test/api/spa/users'),
        jsonRequest('/api/spa/users', {}),
        jsonRequest('/api/spa/users/1', {}, { method: 'PUT' }),
        new Request('https://sistem-toko-api.test/api/spa/users/1', { method: 'DELETE' }),
        new Request('https://sistem-toko-api.test/api/spa/profile'),
        jsonRequest('/api/spa/profile', {}, { method: 'PUT' }),
        new Request('https://sistem-toko-api.test/api/spa/me'),
        jsonRequest('/api/spa/logout', {}, { method: 'POST' }),
    ];

    for (const request of requests) {
        const noTokenResponse = await worker.fetch(request.clone(), environment);
        const noTokenPayload = await noTokenResponse.json();
        const headers = new Headers(request.headers);
        headers.set('Authorization', 'Bearer invalid-token');
        const invalidTokenRequest = new Request(request.clone(), { headers });
        const invalidTokenResponse = await worker.fetch(invalidTokenRequest, environment);
        const invalidTokenPayload = await invalidTokenResponse.json();

        assert.equal(noTokenResponse.status, 401, request.url);
        assert.equal(noTokenResponse.headers.get('Content-Type'), 'application/json');
        assert.equal(noTokenPayload.message, 'Sesi tidak valid atau telah berakhir.');
        assert.equal(invalidTokenResponse.status, 401, request.url);
        assert.equal(invalidTokenPayload.message, 'Sesi tidak valid atau telah berakhir.');
    }
});

test('empty authentication and transaction payloads fail without writing data', async () => {
    const database = new MockD1();
    database.users.push({
        id: 1,
        name: 'Master User',
        username: 'master',
        password: await hash('correct-password', 4),
        role: 'master',
    });
    const environment = createEnvironment(database);
    const loginResponse = await worker.fetch(
        jsonRequest('/api/spa/login', {}),
        environment,
    );
    const loginPayload = await loginResponse.json();
    const validLogin = await worker.fetch(
        jsonRequest('/api/spa/login', { username: 'master', password: 'correct-password' }),
        environment,
    );
    const { token } = await validLogin.json();
    const transactionResponse = await worker.fetch(
        jsonRequest('/api/spa/transactions', {}, { Authorization: `Bearer ${token}` }),
        environment,
    );
    const transactionPayload = await transactionResponse.json();

    assert.equal(loginResponse.status, 401);
    assert.equal(loginPayload.message, 'Username atau password salah');
    assert.equal(transactionResponse.status, 422);
    assert.equal(transactionPayload.message, 'Data transaksi tidak valid.');
    assert.ok(Object.keys(transactionPayload.errors).length > 0);
    assert.equal(database.transactions.length, 0);
});

test('transaction detail returns nested records and 404 for an unknown vehicle', async () => {
    const database = new MockD1();
    const vehicle = vehicleFixture(71);
    Object.assign(vehicle, {
        plate_number: 'B 7171 FULL',
        brand: 'Toyota',
        model: 'Avanza',
        year: 2022,
        transmission: 'automatic',
        color: 'Hitam',
        chassis_number: 'CHASSIS-FULL',
        engine_number: 'ENGINE-FULL',
        mileage_km: 45000,
        owner_name: 'STNK Owner',
        manager_name: 'Unit Manager',
        buy_date: '2026-01-15',
        sell_date: '2026-03-05',
        buy_price: 100000000,
        purchase_operational: 1500000,
        other_costs: 500000,
        total_capital: 104500000,
        selling_price: 140000000,
        down_payment: 20000000,
        settlement_amount: 30000000,
        leasing_disbursement: 10000000,
        remaining_payment: 80000000,
        gross_profit: 35500000,
        showroom_fee: 3500000,
        total_intertine_expense: 4500000,
        net_profit: 31000000,
        leasing_ripan: 1000000,
        insurance_ripan: 200000,
        booster_ripan: 300000,
        seller_name: 'Nama Penjual Utuh',
        seller_nik: '3171000000000001',
        seller_phone: '081234567890',
        seller_address: 'Jl. Penjual No. 71',
        broker_name: 'Broker Utuh',
        additional_repairs: JSON.stringify([
            { name: 'Servis Mesin', date: '2026-01-20', cost: 2000000 },
        ]),
        tax_items: JSON.stringify([
            { name: 'Pajak Tahunan', date: '2026-02-01', cost: 500000 },
        ]),
        intertine_expenses: JSON.stringify([
            { name: 'KR Jual', cost: 100000 },
            { name: 'OP Survei', cost: 200000 },
            { name: 'Fee CMO', cost: 300000 },
        ]),
        leasing_options: JSON.stringify([{
            name: 'Leasing Utama',
            disbursement: 100000000,
            selling_price: 140000000,
            dp: 40000000,
            installment_3yr: 3500000,
            installment_4yr: 2800000,
            installment_5yr: 2400000,
        }]),
    });
    database.transactions.push(vehicle);
    database.vehiclePhotos.push({
        id: 8,
        car_transaction_id: vehicle.id,
        path: 'vehicles/71/cover.webp',
        is_cover: 1,
        position: 0,
    });
    database.saleNotes.push({
        id: 11,
        car_transaction_id: vehicle.id,
        nama_konsumen: 'Pembeli',
        buyer_nik: '3171000000000002',
        buyer_phone: '08123456789',
        buyer_address: 'Jakarta',
        created_by: 1,
    });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/71',
        { headers },
    ), environment);
    const payload = await response.json();
    const editResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/71/edit',
        { headers },
    ), environment);
    const editPayload = await editResponse.json();
    const missingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/999',
        { headers },
    ), environment);

    assert.equal(response.status, 200);
    assert.equal(environment.VEHICLE_PHOTOS_PUBLIC_URL, '');
    assert.equal(payload.data.id, 71);
    assert.equal(payload.data.vehicle_status, 'sold');
    assert.equal(payload.data.plate_number, 'B 7171 FULL');
    assert.equal(payload.data.brand, 'Toyota');
    assert.equal(payload.data.model, 'Avanza');
    assert.equal(payload.data.year, 2022);
    assert.equal(payload.data.transmission, 'automatic');
    assert.equal(payload.data.color, 'Hitam');
    assert.equal(payload.data.chassis_number, 'CHASSIS-FULL');
    assert.equal(payload.data.engine_number, 'ENGINE-FULL');
    assert.equal(payload.data.mileage_km, 45000);
    assert.equal(payload.data.owner_name, 'STNK Owner');
    assert.equal(payload.data.manager_name, 'Unit Manager');
    assert.equal(payload.data.buy_date, '2026-01-15');
    assert.equal(payload.data.sell_date, '2026-03-05');
    assert.equal(payload.data.seller_name, 'Nama Penjual Utuh');
    assert.equal(payload.data.seller_nik, '3171000000000001');
    assert.equal(payload.data.seller_phone, '081234567890');
    assert.equal(payload.data.seller_address, 'Jl. Penjual No. 71');
    assert.equal(payload.data.broker_name, 'Broker Utuh');
    assert.equal(payload.data.purchase_price, 100000000);
    assert.equal(payload.data.purchase_operation, 1500000);
    assert.equal(payload.data.total_cost, 104500000);
    assert.equal(payload.data.tax_amount, 500000);
    assert.equal(payload.data.total_paid, 60000000);
    assert.equal(payload.data.laba_jual, 35500000);
    assert.equal(payload.data.ripan_leasing, 1000000);
    assert.equal(payload.data.ripan_asuransi, 200000);
    assert.equal(payload.data.ripan_booster, 300000);
    assert.deepEqual(payload.data.repairs, [
        { name: 'Servis Mesin', date: '2026-01-20', cost: 2000000 },
    ]);
    assert.equal(payload.data.tax_items[0].name, 'Pajak Tahunan');
    assert.equal(payload.data.intertine_expenses[2].name, 'Fee CMO');
    assert.deepEqual(payload.data.leasing_prices, [{
        name: 'Leasing Utama',
        disbursement: 100000000,
        selling_price: 140000000,
        dp: 40000000,
        installment_3yr: 3500000,
        installment_4yr: 2800000,
        installment_5yr: 2400000,
    }]);
    assert.equal(payload.data.vehicle_photos[0].id, 8);
    assert.equal(payload.data.vehicle_photos[0].is_cover, true);
    assert.equal(
        payload.data.vehicle_photos[0].url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/71/cover.webp',
    );
    assert.equal(
        payload.data.vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/71/cover.webp',
    );
    assert.equal(payload.data.sale_note.nama_konsumen, 'Pembeli');
    assert.equal(payload.data.sale_note.buyer_nik, '3171000000000002');
    assert.equal(editResponse.status, 200);
    assert.equal(editPayload.data.id, 71);
    assert.equal(editPayload.data.vehicle_photos[0].url, payload.data.vehicle_photos[0].url);

    database.users.splice(0, database.users.length);
    const { token: adminToken } = await authenticate(database, environment, 'admin');
    const adminResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/71',
        { headers: { Authorization: `Bearer ${adminToken}` } },
    ), environment);
    const adminPayload = await adminResponse.json();

    assert.equal(adminResponse.status, 200);
    assert.equal(adminPayload.data.plate_number, 'B 7171 FULL');
    assert.equal(adminPayload.data.chassis_number, 'CHASSIS-FULL');
    assert.equal(adminPayload.data.engine_number, 'ENGINE-FULL');
    assert.equal(adminPayload.data.seller_name, 'Nama Penjual Utuh');
    assert.equal(adminPayload.data.seller_nik, '3171000000000001');
    assert.equal(adminPayload.data.seller_phone, '081234567890');
    assert.equal(adminPayload.data.seller_address, 'Jl. Penjual No. 71');
    assert.equal(adminPayload.data.broker_name, 'Broker Utuh');
    assert.equal(adminPayload.data.repairs[0].name, 'Servis Mesin');
    assert.equal(adminPayload.data.tax_amount, 500000);
    assert.equal(adminPayload.data.vehicle_photos.length, 1);
    assert.equal(adminPayload.data.leasing_prices[0].installment_5yr, 2400000);
    assert.equal(adminPayload.data.selling_price, 140000000);
    assert.equal(adminPayload.data.can_view_sensitive_data, false);
    for (const field of ['buy_price', 'purchase_price', 'total_capital', 'total_cost', 'net_profit', 'laba_jual']) {
        assert.equal(field in adminPayload.data, false);
    }

    assert.equal(missingResponse.status, 404);
    assert.equal((await missingResponse.json()).message, 'Data transaksi tidak ditemukan.');
});

test('transaction updates validate data and synchronize photo additions and removals', async () => {
    const database = new MockD1();
    const vehicle = vehicleFixture(72);
    database.transactions.push(vehicle);
    database.vehiclePhotos.push({
        id: 9,
        car_transaction_id: vehicle.id,
        path: 'vehicles/72/old.webp',
        is_cover: 1,
        position: 0,
    });
    const environment = createEnvironment(database);
    environment.VEHICLE_PHOTOS.storedPhotos.set('vehicles/72/old.webp', {});
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const invalidResponse = await worker.fetch(
        jsonRequest('/api/spa/transactions/72', {}, { ...headers, method: 'PUT' }),
        environment,
    );
    const invalidPayload = await invalidResponse.json();
    const formData = new FormData();

    Object.entries(validTransactionPayload({ model: 'Updated model' }))
        .forEach(([key, value]) => formData.append(key, String(value)));
    formData.append('hapus_foto[]', '9');
    formData.append('foto_utama', 'new:0');
    formData.append('_method', 'PUT');
    formData.append('foto_kendaraan[]', new File([new Uint8Array([1, 2, 3])], 'new.webp', {
        type: 'image/webp',
    }));
    const updateResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/72',
        { method: 'POST', headers, body: formData },
    ), environment);

    assert.equal(invalidResponse.status, 422);
    assert.ok(invalidPayload.errors.plate_number);
    assert.equal(updateResponse.status, 200);
    assert.equal(database.transactions[0].model, 'Updated model');
    assert.equal(database.vehiclePhotos.length, 1);
    assert.equal(database.vehiclePhotos[0].is_cover, 1);
    assert.equal(database.vehiclePhotos[0].path.startsWith('vehicles/72/'), true);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.has('vehicles/72/old.webp'), false);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 1);
});

test('transaction deletion removes its D1 row and R2 photos', async () => {
    const database = new MockD1();
    database.transactions.push(vehicleFixture(73, { vehicle_photo_path: 'vehicles/73/legacy.webp' }));
    database.saleNotes.push({
        id: 21,
        car_transaction_id: 73,
        nama_konsumen: 'Pembeli',
        created_by: 1,
    });
    database.payments.push({
        id: 31,
        sale_note_id: 21,
        amount: 1000,
    });
    database.vehiclePhotos.push({
        id: 10,
        car_transaction_id: 73,
        path: 'vehicles/73/legacy.webp',
        is_cover: 1,
        position: 0,
    });
    const environment = createEnvironment(database);
    environment.VEHICLE_PHOTOS.storedPhotos.set('vehicles/73/legacy.webp', {});
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/73',
        { method: 'DELETE', headers },
    ), environment);
    const payload = await response.json();
    const missingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/73',
        { method: 'DELETE', headers },
    ), environment);

    assert.equal(response.status, 200);
    assert.deepEqual(payload, { message: 'Transaksi berhasil dihapus', deleted_id: 73 });
    assert.equal(database.transactions.length, 0);
    assert.equal(database.vehiclePhotos.length, 0);
    assert.equal(database.saleNotes.length, 0);
    assert.equal(database.payments.length, 0);
    assert.equal(environment.VEHICLE_PHOTOS.storedPhotos.size, 0);
    assert.equal(missingResponse.status, 404);
});

test('deleting one vehicle leaves the remaining dashboard and inventory rows available', async () => {
    const database = new MockD1();
    database.transactions.push(
        vehicleFixture(1, { created_at: '2026-11-10 10:00:00' }),
        vehicleFixture(2, {
            plate_number: 'B 2222 XY',
            brand: 'Honda',
            model: 'Brio',
            created_at: '2026-11-11 10:00:00',
        }),
    );
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const deleteResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/transactions/1',
        { method: 'DELETE', headers },
    ), environment);
    const deletePayload = await deleteResponse.json();

    assert.equal(deleteResponse.status, 200);
    assert.deepEqual(deletePayload, { message: 'Transaksi berhasil dihapus', deleted_id: 1 });

    for (const endpoint of ['/api/spa/dashboard', '/api/spa/transactions']) {
        const response = await worker.fetch(new Request(
            `https://sistem-toko-api.test${endpoint}`,
            { headers },
        ), environment);
        const payload = await response.json();

        assert.equal(response.status, 200, endpoint);
        assert.equal(payload.data.length, 1, endpoint);
        assert.equal(payload.data[0].id, 2, endpoint);
        assert.equal(payload.data[0].plate_number, 'B 2222 XY', endpoint);
        assert.doesNotMatch(JSON.stringify(payload), /Data transaksi tidak ditemukan/);
    }
});

test('marketing catalog filters available vehicles and returns a photo gallery', async () => {
    const database = new MockD1();
    const vehicle = vehicleFixture(74);
    database.transactions.push(vehicle);
    database.vehiclePhotos.push({
        id: 11,
        car_transaction_id: vehicle.id,
        path: 'vehicles/74/cover.webp',
        is_cover: 1,
        position: 0,
    });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment, 'marketing');
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/marketing/vehicles?q=Toyota&transmission=manual&year_from=2020&year_to=2024',
        { headers },
    ), environment);
    const payload = await response.json();
    const detailResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/marketing/vehicles/74',
        { headers },
    ), environment);
    const detailPayload = await detailResponse.json();
    const invalidResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/marketing/vehicles?year_from=2030&year_to=2020',
        { headers },
    ), environment);
    const missingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/marketing/vehicles/999',
        { headers },
    ), environment);

    assert.equal(response.status, 200);
    assert.equal(environment.VEHICLE_PHOTOS_PUBLIC_URL, '');
    assert.equal(payload.data.length, 1);
    assert.equal(payload.summary.available_count, 1);
    assert.equal(payload.data[0].id, 74);
    assert.equal(payload.data[0].leasing_options[0].name, 'Leasing');
    assert.equal(
        payload.data[0].vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/74/cover.webp',
    );
    assert.equal(
        payload.data[0].vehicle_photos[0].url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/74/cover.webp',
    );
    assert.equal(detailResponse.status, 200);
    assert.equal(detailPayload.data.vehicle_photos[0].id, 11);
    assert.equal(
        detailPayload.data.vehicle_photos[0].url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/74/cover.webp',
    );
    assert.equal(invalidResponse.status, 422);
    assert.ok((await invalidResponse.json()).errors.year_to);
    assert.equal(missingResponse.status, 404);
});

test('public R2 photo proxy streams registered images and returns 404 for missing keys', async () => {
    const database = new MockD1();
    const key = 'vehicles/80/photo.png';
    const missingKey = 'vehicles/80/missing.png';
    const imageBytes = new Uint8Array([0x89, 0x50, 0x4e, 0x47]);
    database.transactions.push(vehicleFixture(80, { vehicle_photo_path: key }));
    database.vehiclePhotos.push(
        { id: 20, car_transaction_id: 80, path: key, is_cover: 1, position: 0 },
        { id: 21, car_transaction_id: 80, path: missingKey, is_cover: 0, position: 1 },
    );
    const environment = createEnvironment(database);
    const legacyKey = 'vehicles/80/legacy.webp';
    const nestedKey = 'transactions/80/nested-photo.jpg';
    database.vehiclePhotos.push({
        id: 22,
        car_transaction_id: 80,
        path: legacyKey,
        is_cover: 0,
        position: 2,
    });
    database.vehiclePhotos.push({
        id: 23,
        car_transaction_id: 80,
        path: nestedKey,
        is_cover: 0,
        position: 3,
    });
    environment.VEHICLE_PHOTOS.storedPhotos.set(key, {
        bytes: imageBytes,
        options: { httpMetadata: { contentType: 'image/png' } },
    });
    environment.VEHICLE_PHOTOS.storedPhotos.set('80/legacy.webp', {
        bytes: new Uint8Array([0x52, 0x49, 0x46, 0x46]),
        options: { httpMetadata: { contentType: 'image/webp' } },
    });
    environment.VEHICLE_PHOTOS.storedPhotos.set(nestedKey, {
        bytes: new Uint8Array([0xff, 0xd8, 0xff]),
        options: { httpMetadata: { contentType: 'image/jpeg' } },
    });
    const response = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/vehicle-photos/${key}`,
        { headers: { Origin: 'https://sistem-toko.pages.dev' } },
    ), environment);
    const legacyResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/vehicle-photos/${legacyKey}`,
    ), environment);
    const nestedPathResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/vehicle-photos/${nestedKey}`,
    ), environment);
    const missingObjectResponse = await worker.fetch(new Request(
        `https://sistem-toko-api.test/api/vehicle-photos/${missingKey}`,
    ), environment);
    const unknownKeyResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/80/unknown.png',
    ), environment);

    assert.equal(response.status, 200);
    assert.equal(response.headers.get('Content-Type'), 'image/png');
    assert.equal(response.headers.get('Access-Control-Allow-Origin'), '*');
    assert.equal(response.headers.get('Cache-Control'), 'public, max-age=86400');
    assert.deepEqual(new Uint8Array(await response.arrayBuffer()), imageBytes);
    assert.equal(legacyResponse.status, 200);
    assert.equal(legacyResponse.headers.get('Content-Type'), 'image/webp');
    assert.deepEqual(
        new Uint8Array(await legacyResponse.arrayBuffer()),
        new Uint8Array([0x52, 0x49, 0x46, 0x46]),
    );
    assert.equal(nestedPathResponse.status, 200);
    assert.equal(nestedPathResponse.headers.get('Content-Type'), 'image/jpeg');
    assert.deepEqual(
        new Uint8Array(await nestedPathResponse.arrayBuffer()),
        new Uint8Array([0xff, 0xd8, 0xff]),
    );
    assert.equal(missingObjectResponse.status, 404);
    assert.equal(unknownKeyResponse.status, 404);
});

test('sale detail includes payment history and enforces record ownership', async () => {
    const database = new MockD1();
    const { transaction } = saleFixture(database);
    transaction.intertine_expenses = JSON.stringify([
        { name: 'KR Jual', cost: 100000 },
        { name: 'OP Survei', cost: 200000 },
        { name: 'Fee CMO', cost: 300000 },
    ]);
    transaction.vehicle_photo_path = 'vehicles/101/cover.webp';
    database.vehiclePhotos.push({
        id: 7,
        car_transaction_id: 101,
        path: 'vehicles/101/cover.webp',
        is_cover: 1,
        position: 0,
    });
    database.payments.push({
        id: 1,
        sale_note_id: 1,
        amount: 5000000,
        payment_method: 'cash',
        payment_date: '2026-01-02',
        notes: 'DP tambahan',
    });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual/1',
        { headers },
    ), environment);
    const payload = await response.json();
    const missingResponse = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual/999',
        { headers },
    ), environment);

    assert.equal(response.status, 200);
    assert.equal(payload.data.id, 1);
    assert.equal(payload.data.car_transaction.id, 101);
    assert.deepEqual(
        Object.fromEntries([
            'plate_number',
            'brand',
            'model',
            'year',
            'transmission',
            'color',
            'chassis_number',
            'engine_number',
            'mileage_km',
            'owner_name',
            'manager_name',
            'buy_date',
        ].map((field) => [field, payload.data.car_transaction[field]])),
        {
            plate_number: 'B 101 XY',
            brand: 'Toyota',
            model: 'Avanza',
            year: 2022,
            transmission: 'manual',
            color: 'Hitam',
            chassis_number: 'CHASSIS',
            engine_number: 'ENGINE',
            mileage_km: 12000,
            owner_name: 'Pemilik',
            manager_name: 'Manager',
            buy_date: '2026-01-01',
        },
    );
    assert.equal(
        payload.data.car_transaction.vehicle_photo_url,
        'https://sistem-toko-api.test/api/vehicle-photos/vehicles/101/cover.webp',
    );
    assert.equal(payload.data.car_transaction.vehicle_photos[0].is_primary, true);
    assert.deepEqual(payload.data.car_transaction.intertine_expenses, [
        { name: 'KR Jual', cost: 100000 },
        { name: 'OP Survei', cost: 200000 },
        { name: 'Fee CMO', cost: 300000 },
    ]);
    assert.equal(payload.data.payments[0].amount, 5000000);
    assert.equal(payload.data.payment_status, 'unpaid');
    assert.equal(missingResponse.status, 404);
});

test('sale updates validate input and persist buyer, payment, and commission changes', async () => {
    const database = new MockD1();
    saleFixture(database);
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const invalidResponse = await worker.fetch(
        jsonRequest('/api/spa/jual/1', {}, { ...headers, method: 'PUT' }),
        environment,
    );
    const invalidPayload = await invalidResponse.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/jual/1', validSalePayload({
            nama_konsumen: 'Pembeli diubah',
            selling_price: 'Rp 160.000.000',
            intertine_expenses: [{ name: 'Fee CMO', cost: 'Rp 500.000' }],
        }), { ...headers, method: 'PUT' }),
        environment,
    );
    const payload = await response.json();

    assert.equal(invalidResponse.status, 422);
    assert.ok(invalidPayload.errors.nama_konsumen);
    assert.equal(response.status, 200);
    assert.equal(payload.data.id, 1);
    assert.equal(database.saleNotes[0].nama_konsumen, 'Pembeli diubah');
    assert.equal(database.transactions[0].selling_price, 160000000);
    assert.equal(database.transactions[0].remaining_payment, 150000000);
    assert.deepEqual(JSON.parse(database.transactions[0].intertine_expenses), [
        { name: 'Biaya lain', cost: 50000 },
        { name: 'Fee CMO', cost: 500000 },
    ]);
});

test('sale deletion restores the vehicle to available stock and cascades payments', async () => {
    const database = new MockD1();
    saleFixture(database);
    database.payments.push({
        id: 1,
        sale_note_id: 1,
        amount: 5000000,
        payment_method: 'cash',
        payment_date: '2026-01-02',
        notes: null,
    });
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const response = await worker.fetch(new Request(
        'https://sistem-toko-api.test/api/spa/jual/1',
        { method: 'DELETE', headers },
    ), environment);
    const payload = await response.json();

    assert.equal(response.status, 200);
    assert.equal(payload.message, 'Data Jual berhasil dihapus.');
    assert.equal(database.transactions[0].sell_date, null);
    assert.equal(database.transactions[0].selling_price, 0);
    assert.equal(database.saleNotes.length, 0);
    assert.equal(database.payments.length, 0);
});

test('sale payments validate amount and record D1 history while reducing the balance', async () => {
    const database = new MockD1();
    saleFixture(database);
    const environment = createEnvironment(database);
    const { token } = await authenticate(database, environment);
    const headers = { Authorization: `Bearer ${token}` };
    const invalidResponse = await worker.fetch(
        jsonRequest('/api/spa/jual/1/payments', {}, headers),
        environment,
    );
    const invalidPayload = await invalidResponse.json();
    const response = await worker.fetch(
        jsonRequest('/api/spa/jual/1/payments', {
            amount: 'Rp 5.000.000',
            payment_method: 'cash',
            payment_date: '2026-01-02',
            notes: 'Pembayaran tambahan',
        }, headers),
        environment,
    );
    const payload = await response.json();

    assert.equal(invalidResponse.status, 422);
    assert.ok(invalidPayload.errors.amount);
    assert.equal(response.status, 201);
    assert.equal(payload.message, 'Pelunasan berhasil dicatat.');
    assert.equal(database.payments.length, 1);
    assert.equal(database.payments[0].amount, 5000000);
    assert.equal(database.transactions[0].settlement_amount, 5000000);
    assert.equal(database.transactions[0].remaining_payment, 135000000);
});
