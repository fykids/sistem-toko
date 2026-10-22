export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    const corsHeaders = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type",
    };

    if (request.method === "OPTIONS") {
      return new Response(null, { headers: corsHeaders });
    }

    // API Endpoint: Get Transaksi
    if (url.pathname === "/api/transactions" && request.method === "GET") {
      try {
        const { results } = await env.DB.prepare("SELECT * FROM car_transactions").all();
        return Response.json(results, { headers: corsHeaders });
      } catch (e) {
        return Response.json({ error: e.message }, { status: 500, headers: corsHeaders });
      }
    }

    // Serve asset statis frontend secara default
    return env.ASSETS.fetch(request);
  }
};