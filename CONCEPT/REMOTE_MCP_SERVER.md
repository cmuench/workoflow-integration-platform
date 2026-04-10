# Remote MCP Server Integration

## Overview

The platform supports connecting to vendor-hosted Remote MCP Servers via OAuth 2.1 with Dynamic Client Registration (DCR) and PKCE. Users can one-click connect from a curated catalog or manually add custom servers.

## Architecture

```
User clicks server in catalog
  → Platform creates IntegrationConfig (auto)
  → Discovers OAuth metadata (/.well-known/oauth-authorization-server)
  → Registers client via DCR (POST /register)
  → Generates PKCE (code_verifier + S256 challenge)
  → Redirects user to vendor consent page
  → Callback exchanges code for tokens
  → Tools discovered via MCP protocol (initialize → tools/list)
  → Tools served via platform API (/api/mcp/tools, /api/integrations/)
  → Token auto-refresh on expiry
```

## Key Files

| File | Purpose |
|---|---|
| `src/Config/RemoteMcpServerCatalog.php` | Static catalog of verified MCP servers |
| `src/Service/Integration/RemoteMcpOAuthService.php` | OAuth2 protocol: metadata discovery, DCR, PKCE, token exchange/refresh |
| `src/Service/Integration/RemoteMcpService.php` | MCP protocol: initialize, tools/list, tools/call, auth headers |
| `src/Integration/UserIntegrations/RemoteMcpIntegration.php` | Integration plugin (credential fields, validation, system prompt) |
| `src/Controller/IntegrationOAuthController.php` | OAuth start + callback routes |
| `src/Controller/IntegrationController.php` | Quick-connect route, detect-auth endpoint, setup form |
| `templates/components/remote_mcp_table.html.twig` | Dropdown catalog UI |

## Server Catalog

### Implemented — OAuth2 DCR Quick-Connect (51 servers)

Full flow verified: DCR registration + authorization redirect with production redirect URI.

| # | Name | MCP Server URL | Logo | Status |
|---|---|---|---|---|
| 1 | Amplitude | `https://mcp.amplitude.com/mcp` | `public/images/logos/mcp-servers/amplitude.jpg` | Implemented |
| 2 | Apollo.io | `https://mcp.apollo.io/mcp` | `public/images/logos/mcp-servers/apollo-io.svg` | Implemented |
| 3 | Atlassian Rovo | `https://mcp.atlassian.com/v1/mcp` | `public/images/logos/mcp-servers/atlassian-rovo.jpg` | Implemented |
| 4 | Attio | `https://mcp.attio.com/mcp` | `public/images/logos/mcp-servers/attio.svg` | Implemented |
| 5 | Candid | `https://mcp.candid.org/mcp` | `public/images/logos/mcp-servers/candid.jpg` | Implemented |
| 6 | CB Insights | `https://mcp.cbinsights.com/mcp` | `public/images/logos/mcp-servers/cb-insights.png` | Implemented |
| 7 | Close | `https://mcp.close.com/mcp` | `public/images/logos/mcp-servers/close.jpg` | Implemented |
| 8 | Cloudflare | `https://mcp.cloudflare.com/mcp` | `public/images/logos/mcp-servers/cloudflare.jpg` | Implemented |
| 9 | Common Room | `https://mcp.commonroom.io/mcp` | `public/images/logos/mcp-servers/common-room.svg` | Implemented |
| 10 | Consensus | `https://mcp.consensus.app/mcp` | `public/images/logos/mcp-servers/consensus.svg` | Implemented |
| 11 | Context7 | `https://mcp.context7.com/mcp` | `public/images/logos/mcp-servers/context7.png` | Implemented |
| 12 | Crossbeam | `https://mcp.crossbeam.com/mcp` | `public/images/logos/mcp-servers/crossbeam.svg` | Implemented |
| 13 | Daloopa | `https://mcp.daloopa.com/mcp` | `public/images/logos/mcp-servers/daloopa.svg` | Implemented |
| 14 | Day AI | `https://day.ai/api/mcp` | `public/images/logos/mcp-servers/day-ai.png` | Implemented |
| 15 | FactSet | `https://mcp.factset.com/mcp` | `public/images/logos/mcp-servers/factset-ai-ready-data.png` | Implemented |
| 16 | Fellow.ai | `https://fellow.app/mcp/mcp` | `public/images/logos/mcp-servers/fellow-ai.svg` | Implemented |
| 17 | Granola | `https://mcp.granola.ai/mcp` | `public/images/logos/mcp-servers/granola.svg` | Implemented |
| 18 | Hugging Face | `https://huggingface.co/mcp` | `public/images/logos/mcp-servers/hugging-face.jpg` | Implemented |
| 19 | Jam | `https://mcp.jam.dev/mcp` | `public/images/logos/mcp-servers/jam.jpg` | Implemented |
| 20 | Jotform | `https://mcp.jotform.com/mcp` | `public/images/logos/mcp-servers/jotform.jpg` | Implemented |
| 21 | Klaviyo | `https://mcp.klaviyo.com/mcp` | `public/images/logos/mcp-servers/klaviyo.png` | Implemented |
| 22 | Krisp | `https://mcp.krisp.ai/mcp` | `public/images/logos/mcp-servers/krisp.svg` | Implemented |
| 23 | LILT | `https://mcp.lilt.com/mcp` | `public/images/logos/mcp-servers/lilt.png` | Implemented |
| 24 | Linear | `https://mcp.linear.app/mcp` | `public/images/logos/mcp-servers/linear.jpg` | Implemented |
| 25 | Local Falcon | `https://mcp.localfalcon.com/mcp` | `public/images/logos/mcp-servers/local-falcon.svg` | Implemented |
| 26 | Lumin | `https://mcp.luminpdf.com/mcp` | `public/images/logos/mcp-servers/lumin.svg` | Implemented |
| 27 | MailerLite | `https://mcp.mailerlite.com/mcp` | `public/images/logos/mcp-servers/mailerlite.jpg` | Implemented |
| 28 | Make | `https://mcp.make.com/mcp` | `public/images/logos/mcp-servers/make.svg` | Implemented |
| 29 | Mem | `https://mcp.mem.ai/mcp` | `public/images/logos/mcp-servers/mem.svg` | Implemented |
| 30 | Mercury | `https://mcp.mercury.com/mcp` | `public/images/logos/mcp-servers/mercury.svg` | Implemented |
| 31 | Miro | `https://mcp.miro.com/mcp` | `public/images/logos/mcp-servers/miro.svg` | Implemented |
| 32 | Mixpanel | `https://mcp.mixpanel.com/mcp` | `public/images/logos/mcp-servers/mixpanel.svg` | Implemented |
| 33 | monday.com | `https://mcp.monday.com/mcp` | `public/images/logos/mcp-servers/monday.jpg` | Implemented |
| 34 | Morningstar | `https://mcp.morningstar.com/mcp` | `public/images/logos/mcp-servers/morningstar.jpg` | Implemented |
| 35 | Notion | `https://mcp.notion.com/mcp` | `public/images/logos/mcp-servers/notion.jpg` | Implemented |
| 36 | PayPal | `https://mcp.paypal.com/mcp` | `public/images/logos/mcp-servers/paypal.jpg` | Implemented |
| 37 | PostHog | `https://mcp.posthog.com/mcp` | `public/images/logos/mcp-servers/posthog.svg` | Implemented |
| 38 | Postman | `https://mcp.postman.com/mcp` | `public/images/logos/mcp-servers/postman.png` | Implemented |
| 39 | Pylon | `https://mcp.usepylon.com/mcp` | `public/images/logos/mcp-servers/pylon.svg` | Implemented |
| 40 | Ramp | `https://mcp.ramp.com/mcp` | `public/images/logos/mcp-servers/ramp.jpg` | Implemented |
| 41 | Razorpay | `https://mcp.razorpay.com/mcp` | `public/images/logos/mcp-servers/razorpay.jpg` | Implemented |
| 42 | Sanity | `https://mcp.sanity.io/mcp` | `public/images/logos/mcp-servers/sanity.svg` | Implemented |
| 43 | Sentry | `https://mcp.sentry.dev/mcp` | `public/images/logos/mcp-servers/sentry.jpg` | Implemented |
| 44 | Stripe | `https://mcp.stripe.com/` | `public/images/logos/mcp-servers/stripe.jpg` | Implemented |
| 45 | Supermetrics | `https://mcp.supermetrics.com/mcp` | `public/images/logos/mcp-servers/supermetrics.svg` | Implemented |
| 46 | Synapse.org | `https://mcp.synapse.org/mcp` | `public/images/logos/mcp-servers/synapse-org.jpg` | Implemented |
| 47 | Tavily | `https://mcp.tavily.com/mcp` | `public/images/logos/mcp-servers/tavily.svg` | Implemented |
| 48 | Webflow | `https://mcp.webflow.com/mcp` | `public/images/logos/mcp-servers/webflow.svg` | Implemented |
| 49 | Windsor.ai | `https://mcp.windsor.ai/mcp` | `public/images/logos/mcp-servers/windsor-ai.png` | Implemented |
| 50 | Wix | `https://mcp.wix.com/mcp` | `public/images/logos/mcp-servers/wix.png` | Implemented |
| 51 | Zapier | `https://mcp.zapier.com/api/mcp/mcp` | `public/images/logos/mcp-servers/zapier.jpg` | Implemented |

### Blocked — Vendor Restrictions (9 servers)

These servers have OAuth metadata but reject our production redirect URI or block public DCR.

| # | Name | MCP Server URL | Logo | Failure Reason |
|---|---|---|---|---|
| 1 | Canva | `https://mcp.canva.com/mcp` | `public/images/logos/mcp-servers/canva.jpg` | DCR succeeds but authorize rejects redirect URI ("must be from allowed host") |
| 2 | Figma | `https://mcp.figma.com/mcp` | `public/images/logos/mcp-servers/figma.jpg` | DCR returns 403 Forbidden (registration closed to public) |
| 3 | Intercom | `https://mcp.intercom.com/mcp` | `public/images/logos/mcp-servers/intercom.jpg` | DCR rejects non-allowlisted redirect URIs (400) |
| 4 | MSCI | `https://mcp.msci.com/mcp` | `public/images/logos/mcp-servers/msci.png` | DCR expects incompatible payload format ("unable to bind input to SinglePageAppType") |
| 5 | MT Newswires | `https://mcp.mtnewswires.com/mcp` | `public/images/logos/mcp-servers/mt-newswires.jpg` | "redirect URI domain not in allowed list" |
| 6 | Quartr | `https://mcp.quartr.com/mcp` | `public/images/logos/mcp-servers/quartr.png` | "redirect URI host not allowed" |
| 7 | Square | `https://mcp.squareup.com/mcp` | `public/images/logos/mcp-servers/square.jpg` | "Invalid redirect URI - domain not in allowlist" |
| 8 | Vercel | `https://mcp.vercel.com/` | `public/images/logos/mcp-servers/vercel.jpg` | Only accepts localhost redirect URIs (native app policy) |
| 9 | ZoomInfo | `https://mcp.zoominfo.com/mcp` | `public/images/logos/mcp-servers/zoominfo.jpg` | "Vendor workoflow-platform not found in approved vendors" |

### Blocked — Partial DCR (3 servers)

DCR works with localhost but rejects production redirect URIs.

| # | Name | MCP Server URL | Logo | Failure Reason |
|---|---|---|---|---|
| 1 | Asana | `https://mcp.asana.com/v2/mcp` | `public/images/logos/mcp-servers/asana.jpg` | Only accepts localhost redirect URIs |
| 2 | ClickUp | `https://mcp.clickup.com/mcp` | `public/images/logos/mcp-servers/clickup.svg` | Requires manual allowlisting via form |
| 3 | Coupler.io | `https://mcp.coupler.io/mcp` | `public/images/logos/mcp-servers/coupler-io.jpg` | Rejects all redirect URIs tested |

### Not Available — No OAuth/DCR or Unreachable (50+ servers)

These servers were tested but either have no MCP endpoint, no OAuth metadata, or DNS does not resolve.

| # | Name | Tested URL | Logo | Status |
|---|---|---|---|---|
| 1 | Ahrefs | `https://mcp.ahrefs.com` | `public/images/logos/mcp-servers/ahrefs.svg` | No OAuth metadata (404) |
| 2 | AirOps | `https://mcp.airops.com` | `public/images/logos/mcp-servers/airops.svg` | DNS not resolved |
| 3 | Airtable | `https://mcp.airtable.com` | `public/images/logos/mcp-servers/airtable.svg` | No OAuth metadata (404) |
| 4 | Aura | `https://mcp.aura.com` | `public/images/logos/mcp-servers/aura.jpg` | DNS not resolved |
| 5 | BioRender | `https://mcp.biorender.com` | `public/images/logos/mcp-servers/biorender.jpg` | DNS not resolved |
| 6 | Bitly | `https://mcp.bitly.com` | `public/images/logos/mcp-servers/bitly.svg` | Redirects to marketing page |
| 7 | Calendly | `https://mcp.calendly.com` | `public/images/logos/mcp-servers/calendly.svg` | No OAuth metadata (404) |
| 8 | Campfire | `https://mcp.campfire.to` | `public/images/logos/mcp-servers/campfire.jpg` | DNS not resolved |
| 9 | CData | `https://mcp.cdata.com` | `public/images/logos/mcp-servers/cdata.svg` | DNS not resolved |
| 10 | Chronograph | `https://mcp.chronograph.pe` | `public/images/logos/mcp-servers/chronograph.jpg` | Returns HTML, not OAuth metadata |
| 11 | Circleback | `https://mcp.circleback.ai` | `public/images/logos/mcp-servers/circleback.svg` | DNS not resolved |
| 12 | Clarify | `https://mcp.clarify.ai` | `public/images/logos/mcp-servers/clarify.svg` | DNS not resolved |
| 13 | Clay | `https://mcp.clay.com` | `public/images/logos/mcp-servers/clay.svg` | DNS not resolved |
| 14 | Cloudinary | `https://mcp.cloudinary.com` | `public/images/logos/mcp-servers/cloudinary.jpg` | No OAuth metadata (404) |
| 15 | Egnyte | `https://mcp.egnyte.com` | `public/images/logos/mcp-servers/egnyte.jpg` | No OAuth metadata (404) |
| 16 | Fever | `https://mcp.fever.co` | `public/images/logos/mcp-servers/fever-event-discovery.svg` | DNS not resolved |
| 17 | Fireflies | `https://mcp.fireflies.ai` | `public/images/logos/mcp-servers/fireflies.jpg` | DNS not resolved |
| 18 | Gainsight | `https://mcp.gainsight.com` | `public/images/logos/mcp-servers/gainsight-staircase-ai.png` | DNS not resolved |
| 19 | Gamma | `https://mcp.gamma.app` | `public/images/logos/mcp-servers/gamma.png` | No OAuth metadata (404) |
| 20 | Guru | `https://mcp.getguru.com` | `public/images/logos/mcp-servers/guru.svg` | DNS not resolved |
| 21 | Gusto | `https://mcp.gusto.com` | `public/images/logos/mcp-servers/gusto.png` | DNS not resolved |
| 22 | Harmonic | `https://mcp.harmonic.ai` | `public/images/logos/mcp-servers/harmonic.png` | DNS not resolved |
| 23 | Honeycomb | `https://mcp.honeycomb.io` | `public/images/logos/mcp-servers/honeycomb.svg` | No OAuth metadata (404) |
| 24 | Jentic | `https://mcp.jentic.com` | `public/images/logos/mcp-servers/jentic.png` | DNS not resolved |
| 25 | LegalZoom | `https://mcp.legalzoom.com` | `public/images/logos/mcp-servers/legalzoom.svg` | DNS not resolved |
| 26 | LunarCrush | `https://mcp.lunarcrush.com` | `public/images/logos/mcp-servers/lunarcrush.png` | DNS not resolved |
| 27 | Magic Patterns | `https://mcp.magicpatterns.com` | `public/images/logos/mcp-servers/magic-patterns.jpg` | No OAuth metadata (404) |
| 28 | Medidata | `https://mcp.medidata.com` | `public/images/logos/mcp-servers/medidata.svg` | DNS not resolved |
| 29 | Melon | `https://mcp.melon.co` | `public/images/logos/mcp-servers/melon.jpg` | DNS not resolved |
| 30 | Moody's | `https://mcp.moodys.com` | `public/images/logos/mcp-servers/moody-s-analytics.jpg` | DNS not resolved |
| 31 | Netlify | `https://mcp.netlify.com` | `public/images/logos/mcp-servers/netlify.jpg` | No OAuth metadata (404) |
| 32 | Omni Analytics | `https://mcp.omni.co` | `public/images/logos/mcp-servers/omni-analytics.jpg` | DNS not resolved |
| 33 | Outreach | `https://mcp.outreach.io` | `public/images/logos/mcp-servers/outreach.png` | DNS not resolved |
| 34 | PitchBook | `https://mcp.pitchbook.com` | `public/images/logos/mcp-servers/pitchbook.jpg` | DNS not resolved |
| 35 | Plaid | `https://mcp.plaid.com` | `public/images/logos/mcp-servers/plaid.jpg` | DNS not resolved |
| 36 | Process Street | `https://mcp.process.st` | `public/images/logos/mcp-servers/process-street.svg` | 403 - Missing Authentication Token |
| 37 | S&P Global | `https://mcp.spglobal.com` | `public/images/logos/mcp-servers/s-p-global.jpg` | DNS not resolved |
| 38 | Scholar Gateway | `https://mcp.scholargateway.com` | `public/images/logos/mcp-servers/scholar-gateway.svg` | DNS not resolved |
| 39 | SignNow | `https://mcp.signnow.com` | `public/images/logos/mcp-servers/signnow.svg` | Redirect to marketing (no OAuth) |
| 40 | Similarweb | `https://mcp.similarweb.com` | `public/images/logos/mcp-servers/similarweb.jpg` | No OAuth metadata (404) |
| 41 | Sprouts | `https://mcp.sproutsai.com` | `public/images/logos/mcp-servers/sprouts-data-intelligence.svg` | DNS not resolved |
| 42 | Stytch | `https://mcp.stytch.com` | `public/images/logos/mcp-servers/stytch.jpg` | DNS not resolved |
| 43 | Tango | `https://mcp.tango.us` | `public/images/logos/mcp-servers/tango.png` | DNS not resolved |
| 44 | Ticket Tailor | `https://mcp.tickettailor.com` | `public/images/logos/mcp-servers/ticket-tailor.jpg` | DNS not resolved |
| 45 | Vibe Prospecting | `https://mcp.vibeprospecting.com` | `public/images/logos/mcp-servers/vibe-prospecting.png` | DNS not resolved |
| 46 | WordPress.com | `https://mcp.wordpress.com` | `public/images/logos/mcp-servers/wordpress-com.png` | Redirect, no OAuth metadata |
| 47 | Yardi | `https://mcp.yardi.com` | `public/images/logos/mcp-servers/yardi-virtuoso.jpg` | DNS not resolved |

## Notes

### DCR Client Secret Handling

Some servers return a `client_secret` during DCR even when `token_endpoint_auth_method: "none"` is requested. The platform stores and uses these secrets when exchanging codes for tokens:

- **Krisp** — returns `client_secret`, expects `client_secret_basic`
- **Jotform** — returns `client_secret`, expects `client_secret_post`
- **Mercury** — returns `client_secret`, expects `client_secret_basic`
- **Razorpay** — returns `client_secret`, expects `client_secret_post`
- **Supermetrics** — returns `client_secret`
- **Common Room** — returns `client_secret` (Auth0-based)
- **Crossbeam** — returns `client_secret` (Auth0-based)

### Domain Allowlist Requirements

Some servers require the platform domain to be added to an allowlist by the user's organization admin:

- **Atlassian Rovo** — Atlassian Admin > Apps > AI settings > Rovo MCP server > Add domain

### Custom Server Support

Users can always add any MCP server manually via "Custom MCP Server" in the dropdown, which goes to the standard setup form supporting all auth types: none, bearer, api_key, basic, oauth2.

### Verification Date

All servers verified on 2026-03-30 via live probing from `subscribe-workflows.vcec.cloud`.
