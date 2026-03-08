const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const imageUpload = require(path.resolve(__dirname, '../../assets/js/image-upload.js'));

const repoRoot = process.cwd();
const layouts = JSON.parse(fs.readFileSync(path.resolve(repoRoot, 'tests/fixtures/images/layouts_response.json'), 'utf8'));
const composeFixture = JSON.parse(fs.readFileSync(path.resolve(repoRoot, 'tests/fixtures/images/compose_response.json'), 'utf8'));

const first = imageUpload.normalizeAttachment({
    id: 101,
    title: 'Hero frame',
    url: 'https://example.test/uploads/hero.jpg',
    width: 1600,
    height: 900
});

const second = imageUpload.normalizeAttachment({
    id: 102,
    title: 'Portrait card',
    url: 'https://example.test/uploads/portrait.jpg',
    width: 900,
    height: 1200
});

assert.equal(first.orientation, 'landscape');
assert.equal(second.orientation, 'portrait');

const selectedMarkup = imageUpload.buildSelectedImagesMarkup([first, second]);
assert.match(selectedMarkup, /Hero frame/);
assert.match(selectedMarkup, /portrait/);

const layoutMarkup = imageUpload.buildLayoutMarkup(layouts, [first, second], layouts[0].layout_id);
assert.match(layoutMarkup, /layout_grid_2x2/);
assert.match(layoutMarkup, /Preview/);

const previewState = imageUpload.buildPreviewState(layouts[0], [first, second], composeFixture);
assert.equal(previewState.layoutId, 'layout_grid_2x2');
assert.equal(previewState.previewUrl, composeFixture.preview_url);
assert.equal(previewState.mapping[0].image_id, '101');

const previewMarkup = imageUpload.renderPreviewMarkup(previewState, [first, second]);
assert.match(previewMarkup.frame, /compose_001\.jpg/);
assert.match(previewMarkup.meta, /Hero frame/);

const payload = imageUpload.buildComposePayload({
    referenceId: 'phase3-demo-post-1',
    postId: 0,
    activeLayout: layouts[0],
    previewUrl: composeFixture.preview_url,
    composedImageId: composeFixture.composed_image_id,
    selectedImages: [first, second]
});

assert.equal(payload.layout_id, 'layout_grid_2x2');
assert.equal(payload.mapping.length, 2);
assert.equal(payload.mapping[1].slot_index, 1);

const reordered = imageUpload.reorderImages([first, second], 0, 1);
assert.equal(reordered[0].id, '102');

console.log('image_upload.test.js: PASS');
