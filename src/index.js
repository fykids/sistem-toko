export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Header CORS agar Frontend bisa akses API tanpa terblokir
    const corsHeaders = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type, Authorization",
    };

    if (request.method === "OPTIONS") {
      return new Response(null, { headers: corsHeaders });
    }

    // 1. GET: Ambil Daftar Transaksi Kendaraan
    if (url.pathname === "/api/transactions" && request.method === "GET") {
      try {
        const { results } = await env.DB.prepare(
          "SELECT * FROM car_transactions ORDER BY created_at DESC"
        ).all();
        return Response.json(results, { headers: corsHeaders });
      } catch (err) {
        return Response.json({ error: err.message }, { status: 500, headers: corsHeaders });
      }
    }

    // 2. POST: Tambah Transaksi Mobil Baru
    if (url.pathname === "/api/transactions" && request.method === "POST") {
      try {
        const data = await request.json();
        const { success } = await env.DB.prepare(`
          INSERT INTO car_transactions (
            plate_number, manager_name, brand, model, year, 
            buy_price, selling_price, total_capital, net_profit
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).bind(
          data.plate_number || '',
          data.manager_name || '',
          data.brand || '',
          data.model || '',
          data.year || 0,
          data.buy_price || 0,
          data.selling_price || 0,
          data.total_capital || 0,
          data.net_profit || 0
        ).run();

        return Response.json({ success }, { status: 201, headers: corsHeaders });
      } catch (err) {
        return Response.json({ error: err.message }, { status: 500, headers: corsHeaders });
      }
    }

    // Default response jika route tidak ditemukan
    return new Response("API POS Toko Mobil (Cloudflare D1) Aktif", { 
      status: 200, 
      headers: corsHeaders 
    });
  }
};