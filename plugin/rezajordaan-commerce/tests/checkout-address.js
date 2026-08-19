#!/usr/bin/env node
"use strict";

const assert = require("assert");
const cities = require("../data/iran-cities.json");

const aliases = {
	AL: "ABZ",
	TE: "THR",
	IS: "ESF",
};

const labels = {
	البرز: "ABZ",
	تهران: "THR",
	اصفهان: "ESF",
};

function canonicalState(raw, labelText, cityMap, aliasMap, labelMap) {
	const value = String(raw || "").trim();
	const upper = value.toUpperCase();
	const name = String(labelText || value)
		.replace(/ي/g, "ی")
		.replace(/ك/g, "ک")
		.replace(/\s+/g, " ")
		.trim();

	if (cityMap[upper]) {
		return upper;
	}
	if (aliasMap[upper]) {
		return aliasMap[upper];
	}
	if (labelMap[value]) {
		return labelMap[value];
	}
	if (labelMap[name]) {
		return labelMap[name];
	}
	return "";
}

function cityOptions(state, labelText) {
	const code = canonicalState(state, labelText, cities, aliases, labels);
	return code && cities[code] ? cities[code] : null;
}

assert.strictEqual(Object.keys(cities).length, 31, "31 provinces in JSON");
assert.ok(cities.ABZ.includes("کرج"), "Karaj is in Alborz");
assert.ok(cities.THR.includes("تهران"), "Tehran is in Tehran province");
assert.deepStrictEqual(cityOptions("AL"), cities.ABZ, "AL alias loads Alborz cities");
assert.deepStrictEqual(cityOptions("TE"), cities.THR, "TE alias loads Tehran cities");
assert.deepStrictEqual(cityOptions("البرز"), cities.ABZ, "Persian province name loads Alborz cities");
assert.deepStrictEqual(cityOptions("ABZ", "البرز"), cities.ABZ, "Selected option label still finds Alborz");
assert.strictEqual(cityOptions(""), null, "Unknown province does not wipe the city list");
assert.strictEqual(cityOptions("not-a-province"), null, "Invalid province leaves existing cities in place");

const wcKeys = Object.keys(cities);
assert.ok(!wcKeys.includes("البرز"), "Typed province name is not a WooCommerce state key");
assert.ok(wcKeys.includes("ABZ"), "Selecting البرز must submit ABZ");

console.log(
	"JS city lookup passed:",
	Object.keys(cities).length,
	"provinces,",
	Object.values(cities).reduce((sum, list) => sum + list.length, 0),
	"cities"
);
