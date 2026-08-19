#!/usr/bin/env node
"use strict";

const assert = require("assert");
const cities = require("../data/iran-cities.json");

const aliases = {
	AL: "ABZ",
	TE: "THR",
	IS: "ESF",
};

function canonicalState(code, cityMap, aliasMap) {
	if (!code) {
		return "";
	}
	const upper = String(code).toUpperCase();
	if (Object.prototype.hasOwnProperty.call(cityMap, upper)) {
		return upper;
	}
	if (Object.prototype.hasOwnProperty.call(aliasMap, upper)) {
		return aliasMap[upper];
	}
	return upper;
}

function cityOptions(state) {
	return cityMapCities(canonicalState(state, cities, aliases));
}

function cityMapCities(code) {
	return cities[code] || [];
}

assert.strictEqual(Object.keys(cities).length, 31, "31 provinces in JSON");
assert.ok(cities.ABZ.includes("کرج"), "Karaj is in Alborz");
assert.ok(cities.THR.includes("تهران"), "Tehran is in Tehran province");
assert.ok(!cities.ABZ.includes("تهران"), "Tehran city is not in Alborz");
assert.deepStrictEqual(cityOptions("AL"), cities.ABZ, "AL alias loads Alborz cities");
assert.deepStrictEqual(cityOptions("TE"), cities.THR, "TE alias loads Tehran cities");
assert.strictEqual(cityOptions("").length, 0, "No province means no cities");

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
