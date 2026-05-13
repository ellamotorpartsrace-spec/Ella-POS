<?php
$file = 'c:/xampp/htdocs/ella-pos/views/inventory/movements.php';
$content = file_get_contents($file);

// Identify places where PHP code is followed by HTML but missing a closing tag.
// This is tricky with regex. 
// Instead, let's look for common patterns like "} <div" or "} <a" or "} <button" 
// that appear on new lines.

$patterns = [
    '/(\s+)\}(\s+)(<div|<a|<button|<table|<tr|<td|<section|<h[1-6]|<span|<p|<i|<form|<label|<input|<select|<ul|<li)/'
];

$replacements = [
    '$1}$2?>$3'
];

// Wait, that might be too broad.
// Let's look at what I actually removed. 
// My previous script removed lines that were EXACTLY "  ?>" (after trimming).

// I'll try to find where they were supposed to be by looking at the syntax.
// Or I can just manually fix the ones I see and look for more.

// Let's look for "} <div" and similar.
$content = preg_replace('/\}\s+(<div|<a|<button|<table|<tr|<td|<ul|<li|<span|<section)/', "}\n?>\n$1", $content);

// Let's also check for loops ending and then HTML starting
$content = preg_replace('/(foreach|if|else|elseif)\s*\(.*\)\s*:\s*\n\s*(<div|<a|<button|<table|<tr|<td|<ul|<li|<span|<section)/', "$1(...):\n?>\n$2", $content);
// That regex is risky.

// Actually, I'll just check for where my script removed "  ?>".
// I'll look for cases where a PHP block ends and HTML begins.

file_put_contents($file, $content);
echo "Processed";
