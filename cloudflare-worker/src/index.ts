/**
 * Nexovant Cloudflare Edge Worker
 * 
 * Production Edge Gateway:
 * 1. Global reverse-proxy for Laravel 12 Backend API & FastAPI Cognitive Engine.
 * 2. Edge caching for public news feeds & article dossiers via Cloudflare Cache API.
 * 3. Hotlink Image Shield: Proxies & unblocks third-party news source images on-the-fly
 *    without saving any image binaries to local storage or database (Zero Local Storage).
 * 4. Cloudflare Hyperdrive binding support for accelerated PostgreSQL connectivity.
 */

export interface Env {
  ENVIRONMENT?: string;
  BACKEND_API_URL: string;
  FRONTEND_URL?: string;
  ENABLE_EDGE_CACHE?: string;
  // Cloudflare Hyperdrive binding for PostgreSQL
  HYPERDRIVE?: {
    connectionString: string;
  };
}

export default {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(request.url);

    // 1. Health check endpoint for Cloudflare health monitors
    if (url.pathname === '/healthz' || url.pathname === '/worker-health') {
      return new Response(
        JSON.stringify({
          status: 'healthy',
          service: 'nexovant-cloudflare-gateway',
          timestamp: new Date().toISOString(),
          colo: (request.cf?.colo as string) || 'UNKNOWN',
          ray_id: request.headers.get('cf-ray') || 'local',
          environment: env.ENVIRONMENT || 'production',
          features: {
            edge_cache: env.ENABLE_EDGE_CACHE !== 'false',
            zero_image_storage: true,
            hyperdrive_ready: !!env.HYPERDRIVE,
          },
        }),
        {
          status: 200,
          headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-store',
            ...corsHeaders(url.origin),
          },
        }
      );
    }

    // 2. Handle CORS Preflight OPTIONS requests
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: {
          ...corsHeaders(request.headers.get('Origin') || '*'),
          'Access-Control-Max-Age': '86400',
        },
      });
    }

    // 3. Hotlink Image Shield (/img-shield?url=...)
    // Solves third-party news anti-hotlinking without storing image binaries
    if (url.pathname === '/img-shield' || url.pathname === '/api/v1/media/shield') {
      return handleImageShield(request, ctx);
    }

    // 4. API Requests (/api/*) -> Forward to Backend API Origin
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) {
      return handleApiProxy(request, env, ctx, url);
    }

    // 5. Default: Proxy to Frontend SPA or fallback status
    if (env.FRONTEND_URL) {
      const frontendUrl = new URL(url.pathname + url.search, env.FRONTEND_URL);
      const frontendReq = new Request(frontendUrl.toString(), request);
      return fetch(frontendReq);
    }

    return new Response(
      JSON.stringify({
        name: 'Nexovant Intelligence Edge Gateway',
        status: 'online',
        docs: '/api/v1/documentation',
        version: '1.0.0',
      }),
      {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }
    );
  },
};

/**
 * Proxies API requests to backend origin with Cloudflare Edge Caching
 */
async function handleApiProxy(
  request: Request,
  env: Env,
  ctx: ExecutionContext,
  url: URL
): Promise<Response> {
  const backendBase = (env.BACKEND_API_URL || 'https://api.nexovant.si').replace(/\/+$/, '');
  const targetUrl = new URL(url.pathname + url.search, backendBase);

  // Check Cloudflare edge cache for public GET endpoints
  const cache = caches.default;
  const isCacheable =
    env.ENABLE_EDGE_CACHE !== 'false' &&
    request.method === 'GET' &&
    !request.headers.has('Authorization') &&
    (url.pathname.includes('/articles') ||
      url.pathname.includes('/feed') ||
      url.pathname.includes('/categories') ||
      url.pathname.includes('/topics'));

  if (isCacheable) {
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      const newHeaders = new Headers(cachedResponse.headers);
      newHeaders.set('CF-Edge-Cache-Status', 'HIT');
      return new Response(cachedResponse.body, {
        status: cachedResponse.status,
        statusText: cachedResponse.statusText,
        headers: newHeaders,
      });
    }
  }

  // Clone headers and attach Cloudflare provenance
  const headers = new Headers(request.headers);
  headers.set('Host', new URL(backendBase).host);
  headers.set('X-Forwarded-Proto', url.protocol.replace(':', ''));
  const cfConnectingIp = request.headers.get('cf-connecting-ip');
  if (cfConnectingIp) {
    headers.set('X-Forwarded-For', cfConnectingIp);
    headers.set('X-Real-IP', cfConnectingIp);
  }

  const originRequest = new Request(targetUrl.toString(), {
    method: request.method,
    headers,
    body: request.method !== 'GET' && request.method !== 'HEAD' ? request.body : undefined,
    redirect: 'follow',
  });

  try {
    const originResponse = await fetch(originRequest);
    const responseHeaders = new Headers(originResponse.headers);

    // Apply security & CORS headers
    const cors = corsHeaders(request.headers.get('Origin') || '*');
    Object.entries(cors).forEach(([key, val]) => responseHeaders.set(key, val));
    responseHeaders.set('Referrer-Policy', 'no-referrer');
    responseHeaders.set('X-Content-Type-Options', 'nosniff');

    const edgeResponse = new Response(originResponse.body, {
      status: originResponse.status,
      statusText: originResponse.statusText,
      headers: responseHeaders,
    });

    // Cache successful public GET responses at the edge for 60 seconds
    if (isCacheable && originResponse.status === 200) {
      responseHeaders.set('Cache-Control', 'public, max-age=60, s-maxage=60, stale-while-revalidate=300');
      const responseToCache = new Response(edgeResponse.clone().body, {
        status: edgeResponse.status,
        statusText: edgeResponse.statusText,
        headers: responseHeaders,
      });
      ctx.waitUntil(cache.put(request, responseToCache));
    }

    return edgeResponse;
  } catch (err: any) {
    return new Response(
      JSON.stringify({
        error: 'Edge Gateway Origin Error',
        message: err.message || 'Unable to connect to backend origin server.',
      }),
      {
        status: 502,
        headers: {
          'Content-Type': 'application/json',
          ...corsHeaders(request.headers.get('Origin') || '*'),
        },
      }
    );
  }
}

/**
 * Unblocks and streams external news images directly from source without saving to disk/DB.
 */
async function handleImageShield(request: Request, ctx: ExecutionContext): Promise<Response> {
  const url = new URL(request.url);
  const targetImageUrl = url.searchParams.get('url');

  if (!targetImageUrl || !/^https?:\/\//i.test(targetImageUrl)) {
    return new Response('Invalid target image URL', { status: 400 });
  }

  // Check edge cache for this image
  const cache = caches.default;
  const cached = await cache.match(request);
  if (cached) return cached;

  try {
    const parsedTarget = new URL(targetImageUrl);
    // Request image without leaking the app referer, mimicking direct source visit
    const upstream = await fetch(targetImageUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        'Referer': `${parsedTarget.protocol}//${parsedTarget.host}/`,
      },
      redirect: 'follow',
    });

    if (!upstream.ok) {
      return new Response(`Upstream source returned HTTP ${upstream.status}`, { status: upstream.status });
    }

    const contentType = upstream.headers.get('content-type') || 'image/jpeg';
    const respHeaders = new Headers();
    respHeaders.set('Content-Type', contentType);
    respHeaders.set('Cache-Control', 'public, max-age=86400, s-maxage=86400, stale-while-revalidate=604800');
    respHeaders.set('Access-Control-Allow-Origin', '*');
    respHeaders.set('Timing-Allow-Origin', '*');
    respHeaders.set('X-Content-Type-Options', 'nosniff');

    const imageResponse = new Response(upstream.body, {
      status: 200,
      headers: respHeaders,
    });

    ctx.waitUntil(cache.put(request, imageResponse.clone()));
    return imageResponse;
  } catch (err: any) {
    return new Response('Error retrieving source image: ' + err.message, { status: 502 });
  }
}

/**
 * Standard CORS headers
 */
function corsHeaders(origin: string): Record<string, string> {
  return {
    'Access-Control-Allow-Origin': origin || '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Authorization, Content-Type, Accept, X-Requested-With, Origin, X-CSRF-TOKEN',
    'Access-Control-Allow-Credentials': 'true',
  };
}
