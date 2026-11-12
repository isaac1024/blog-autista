import assert from 'node:assert/strict';
import test from 'node:test';
import { LatestRequest } from '../../../assets/shared/latest_request.js';

test('a new request aborts the one before, and only that one', () => {
    const requests = new LatestRequest(new AbortController().signal);

    const first = requests.next();
    const second = requests.next();

    assert.equal(first.aborted, true);
    assert.equal(second.aborted, false);
});

test('the owner aborting aborts the request running', () => {
    const owner = new AbortController();
    const requests = new LatestRequest(owner.signal);
    const running = requests.next();

    owner.abort();

    assert.equal(running.aborted, true);
});

test('a request started once the owner is gone is aborted already', () => {
    const owner = new AbortController();
    const requests = new LatestRequest(owner.signal);
    owner.abort();

    assert.equal(requests.next().aborted, true);
});
