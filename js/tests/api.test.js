import assert from "node:assert/strict";
import test from "node:test";

import {
  API_BASE,
  login,
  createRide,
  bookRide,
  unbookRide,
  cancelRideAsDriver,
} from "../api.js";

const originalFetch = global.fetch;
const restoreFetch = () => {
  if (typeof originalFetch === "undefined") {
    delete global.fetch;
  } else {
    global.fetch = originalFetch;
  }
};

const makeOkResponse = (body = {}) => ({
  ok: true,
  status: 200,
  json: async () => body,
});

test("API_BASE falls back to backend port when window is undefined", () => {
  assert.equal(API_BASE, "http://127.0.0.1:8001/api");
});

test("login forwards credentials with JSON payload", async () => {
  const calls = [];
  global.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return makeOkResponse({ ok: true });
  };

  try {
    await login({ email: "user@example.test", password: "secret" });
  } finally {
    restoreFetch();
  }

  assert.equal(calls.length, 1);
  const { url, options } = calls[0];
  assert.equal(url, `${API_BASE}/auth/login`);
  assert.equal(options.method, "POST");
  assert.equal(options.credentials, "include");
  assert.equal(options.headers["Content-Type"], "application/json");
  assert.deepEqual(JSON.parse(options.body), {
    email: "user@example.test",
    password: "secret",
  });
});

test("createRide uses POST with provided payload", async () => {
  const calls = [];
  global.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return makeOkResponse({ id: 99 });
  };

  const payload = {
    fromCity: "Paris",
    toCity: "Lyon",
    startAt: "2025-03-01 09:00",
    endAt: "2025-03-01 12:00",
    price: 25,
    vehicleId: 10,
  };

  try {
    await createRide(payload);
  } finally {
    restoreFetch();
  }

  assert.equal(calls.length, 1);
  const { url, options } = calls[0];
  assert.equal(url, `${API_BASE}/rides`);
  assert.equal(options.method, "POST");
  assert.equal(options.credentials, "include");
  assert.equal(options.headers["Content-Type"], "application/json");
  assert.deepEqual(JSON.parse(options.body), payload);
});

test("bookRide posts booking request", async () => {
  const calls = [];
  global.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return makeOkResponse({ ok: true });
  };

  try {
    await bookRide(42, { seats: 2 });
  } finally {
    restoreFetch();
  }

  assert.equal(calls.length, 1);
  const { url, options } = calls[0];
  assert.equal(url, `${API_BASE}/rides/42/book`);
  assert.equal(options.method, "POST");
  assert.equal(options.credentials, "include");
  assert.deepEqual(JSON.parse(options.body), { seats: 2 });
});

test("unbookRide deletes booking", async () => {
  const calls = [];
  global.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return makeOkResponse({ ok: true });
  };

  try {
    await unbookRide(51);
  } finally {
    restoreFetch();
  }

  assert.equal(calls.length, 1);
  const { url, options } = calls[0];
  assert.equal(url, `${API_BASE}/rides/51/book`);
  assert.equal(options.method, "DELETE");
  assert.equal(options.credentials, "include");
});

test("cancelRideAsDriver posts cancellation", async () => {
  const calls = [];
  global.fetch = async (url, options = {}) => {
    calls.push({ url, options });
    return makeOkResponse({ ok: true });
  };

  try {
    await cancelRideAsDriver(77);
  } finally {
    restoreFetch();
  }

  assert.equal(calls.length, 1);
  const { url, options } = calls[0];
  assert.equal(url, `${API_BASE}/rides/77/cancel`);
  assert.equal(options.method, "POST");
  assert.equal(options.credentials, "include");
});

