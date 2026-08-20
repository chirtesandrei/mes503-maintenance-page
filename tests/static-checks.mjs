/**
 * Contrôles statiques exécutés avant chaque publication.
 *
 * Usage : node tests/static-checks.mjs (depuis le dossier du plugin)
 */

import assert from "node:assert/strict";
import { readFile, readdir } from "node:fs/promises";

const root = new URL("../", import.meta.url);

const main = await readFile(new URL("mes503-maintenance-page.php", root), "utf8");
const implementation = await readFile(
  new URL("includes/class-mpmm-mode-maintenance.php", root),
  "utf8",
);
const readme = await readFile(new URL("readme.txt", root), "utf8");
const uninstall = await readFile(new URL("uninstall.php", root), "utf8");
const adminJs = await readFile(new URL("admin/js/admin.js", root), "utf8");
const maintenanceCss = await readFile(new URL("public/css/maintenance.css", root), "utf8");
const distignore = await readFile(new URL(".distignore", root), "utf8");

const TEXT_DOMAIN = "mes503-maintenance-page";

/* --- Cohérence de version -------------------------------------------- */

const headerVersion = main.match(/^\s*\*\s*Version:\s*(\S+)/m)?.[1];
const stableTag = readme.match(/^Stable tag:\s*(\S+)\s*$/m)?.[1];
const constantVersion = main.match(/define\(\s*'MPMM_VERSION',\s*'([^']+)'/)?.[1];

assert.match(headerVersion ?? "", /^\d+\.\d+\.\d+$/, "version d'en-tête absente ou mal formée");
assert.equal(stableTag, headerVersion, "Stable tag et Version divergent");
assert.equal(constantVersion, headerVersion, "MPMM_VERSION et Version divergent");
assert.ok(
  readme.includes(`= ${headerVersion} =`),
  `le changelog ne mentionne pas la version ${headerVersion}`,
);

/* --- En-têtes du plugin ---------------------------------------------- */

assert.match(main, /^\s*\*\s*Text Domain:\s*mes503-maintenance-page\s*$/m);
assert.match(main, /^\s*\*\s*Plugin Name:\s*Mes503 Maintenance Page\s*$/m);
assert.match(main, /^\s*\*\s*Domain Path:\s*\/languages\s*$/m);
assert.doesNotMatch(main, /Update URI:/, "Update URI interdit pour un plugin hébergé sur WordPress.org");
assert.doesNotMatch(
  TEXT_DOMAIN,
  /plugin/i,
  "le slug WordPress.org ne doit pas contenir le terme réservé Plugin",
);

/* --- Sécurité --------------------------------------------------------- */

for (const [name, source] of [
  ["mes503-maintenance-page.php", main],
  ["includes/class-mpmm-mode-maintenance.php", implementation],
]) {
  assert.match(source, /if \( ! defined\( 'ABSPATH' \) \)/, `garde ABSPATH absente dans ${name}`);
}

assert.match(implementation, /register_setting\(/);
assert.match(implementation, /sanitize_callback/);
assert.match(implementation, /current_user_can\( 'manage_options' \)/);
assert.match(implementation, /wp_verify_nonce\(/);
assert.match(implementation, /esc_html\(/);
assert.match(implementation, /esc_url\(/);
assert.doesNotMatch(implementation, /wp_remote_(get|post|request)/, "aucun appel réseau autorisé");
assert.match(uninstall, /WP_UNINSTALL_PLUGIN/);
assert.match(uninstall, /delete_option\( \$mpmm_option_name \)/);

/* --- Réponse publique ------------------------------------------------- */

assert.match(implementation, /status_header\( 503 \)/);
assert.match(implementation, /Retry-After:/);
assert.match(implementation, /X-Robots-Tag: noindex, nofollow, noarchive/);
assert.match(implementation, /wp_register_style\(/, "la feuille publique doit être enregistrée");
assert.match(implementation, /wp_enqueue_style\( 'mpmm-maintenance' \)/, "la feuille publique doit être mise en file");
assert.match(implementation, /wp_add_inline_style\( 'mpmm-maintenance'/, "la couleur dynamique doit passer par l'API Styles");
assert.match(implementation, /wp_print_styles\( 'mpmm-maintenance' \)/, "la page autonome doit imprimer sa feuille mise en file");
assert.doesNotMatch(implementation, /<style\b/i, "aucune balise style ne doit être écrite à la main");
assert.doesNotMatch(implementation, /<script\b/i, "aucune balise script ne doit être écrite à la main");
assert.match(maintenanceCss, /\.mpmm-card/, "la feuille publique est vide ou incomplète");

// robots.txt doit continuer à répondre 200 : un 5xx prolongé sur ce fichier
// fait cesser l'exploration de tout le site.
assert.match(
  implementation,
  /is_robots\(\)\s*\|\|\s*is_favicon\(\)/,
  "robots.txt et favicon doivent être exclus de la page de maintenance",
);

/* --- Internationalisation --------------------------------------------- */

// Toute chaîne passée à une fonction gettext doit porter le bon domaine.
const gettextCalls = [...implementation.matchAll(/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*(['"])(?:\\.|(?!\1).)*\1\s*,\s*(['"])([^'"]*)\2\s*\)/g)];
assert.ok(gettextCalls.length > 0, "aucune chaîne traduisible trouvée");
for (const call of gettextCalls) {
  assert.equal(call[3], TEXT_DOMAIN, `domaine de traduction inattendu : ${call[3]}`);
}

// Les chaînes sources doivent rester en anglais : WordPress.org attend un
// anglais source, le français est livré comme traduction.
const nonAscii = [...implementation.matchAll(/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'([^']*[^\x00-\x7F][^']*)'/g)];
assert.equal(
  nonAscii.length,
  0,
  `chaînes sources non anglaises détectées : ${nonAscii.map((m) => m[1]).join(" | ")}`,
);

// Le sélecteur de média ne doit plus embarquer de texte français en dur.
assert.doesNotMatch(adminJs, /Choisir un logo|Utiliser ce logo/, "libellés JS non traduisibles");
assert.match(adminJs, /window\.mpmmAdmin/, "les libellés JS doivent venir de wp_localize_script");
assert.match(implementation, /wp_localize_script\(/);

// Le catalogue français doit être présent et compilé.
const languages = await readdir(new URL("languages/", root));
assert.ok(languages.includes(`${TEXT_DOMAIN}.pot`), "fichier POT manquant");
assert.ok(languages.includes(`${TEXT_DOMAIN}-fr_FR.po`), "catalogue fr_FR manquant");
assert.ok(languages.includes(`${TEXT_DOMAIN}-fr_FR.mo`), "catalogue fr_FR non compilé");
assert.match(distignore, /^languages\/\*\.po$/m, "les fichiers PO doivent être exclus du paquet");
assert.match(distignore, /^languages\/\*\.mo$/m, "les fichiers MO doivent être exclus du paquet");
assert.doesNotMatch(distignore, /^languages\/\*\.pot$/m, "le catalogue POT doit rester dans le paquet");

const pot = await readFile(new URL(`languages/${TEXT_DOMAIN}.pot`, root), "utf8");
const po = await readFile(new URL(`languages/${TEXT_DOMAIN}-fr_FR.po`, root), "utf8");
const potIds = new Set([...pot.matchAll(/^msgid "(.+)"$/gm)].map((m) => m[1]));
const poIds = new Set([...po.matchAll(/^msgid "(.+)"$/gm)].map((m) => m[1]));
const untranslated = [...potIds].filter((id) => !poIds.has(id));
assert.equal(
  untranslated.length,
  0,
  `chaînes sans traduction française : ${untranslated.join(" | ")}`,
);

/* --- Paquet ----------------------------------------------------------- */

// Les captures ne doivent être annoncées dans le readme que si elles existent,
// et chaque légende doit avoir son fichier.
if (readme.includes("== Screenshots ==")) {
  const captions = readme
    .split("== Screenshots ==")[1]
    .split("==")[0]
    .split("\n")
    .filter((line) => /^\d+\.\s/.test(line.trim()));
  assert.ok(captions.length > 0, "section Screenshots vide");

  const assets = await readdir(new URL("assets/", root));
  for (let i = 1; i <= captions.length; i += 1) {
    assert.ok(
      assets.includes(`screenshot-${i}.png`),
      `légende ${i} annoncée sans fichier screenshot-${i}.png`,
    );
  }
}

console.log(
  `STATIC_CHECKS_OK version=${headerVersion} chaînes=${potIds.size} traduites=${potIds.size - untranslated.length}`,
);
