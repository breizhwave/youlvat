<?php
// Nettoyage du texte riche saisi dans l'éditeur (page contact) : liste blanche de balises,
// aucun attribut sauf href (https://, http://, mailto:, tel:) sur les liens.

const RICH_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote'];
const RICH_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math'];

function sanitize_html(string $html): string
{
    if (trim(html_entity_decode(strip_tags($html)), " \t\n\r\0\x0B\xC2\xA0") === '') return ''; // éditeur vidé (<p><br></p>, &nbsp;…)
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return '';
    rich_clean_node($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

function rich_clean_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if (in_array($tag, RICH_DROP, true)) { $node->removeChild($child); continue; }
            rich_clean_node($child);
            if (!in_array($tag, RICH_TAGS, true)) {
                // balise non autorisée (div, span, font…) : on garde son contenu ; un bloc devient un paragraphe
                if (in_array($tag, ['div', 'section', 'article', 'h1', 'h2', 'h5', 'h6'], true)) {
                    $p = $node->ownerDocument->createElement($tag === 'div' || $tag === 'section' || $tag === 'article' ? 'p' : 'h3');
                    while ($child->firstChild) $p->appendChild($child->firstChild);
                    $node->replaceChild($p, $child);
                } else {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                }
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!($tag === 'a' && strtolower($attr->name) === 'href')) $child->removeAttribute($attr->name);
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!preg_match('~^(https?://|mailto:|tel:)~i', $href)) $child->removeAttribute('href');
                else { $child->setAttribute('target', '_blank'); $child->setAttribute('rel', 'noopener'); }
            }
        } elseif (!($child instanceof DOMText)) {
            $node->removeChild($child); // commentaires, instructions…
        }
    }
}
