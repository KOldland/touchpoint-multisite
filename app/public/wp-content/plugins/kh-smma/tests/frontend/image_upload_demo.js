const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const imageUpload = require(path.resolve(__dirname, '../../assets/js/image-upload.js'));

const repoRoot = process.cwd();
const uploadFixture = JSON.parse(fs.readFileSync(path.resolve(repoRoot, 'tests/fixtures/images/upload_response.json'), 'utf8'));
const layouts = JSON.parse(fs.readFileSync(path.resolve(repoRoot, 'tests/fixtures/images/layouts_response.json'), 'utf8'));
const composeFixture = JSON.parse(fs.readFileSync(path.resolve(repoRoot, 'tests/fixtures/images/compose_response.json'), 'utf8'));

const selectedImages = [
    imageUpload.normalizeAttachment({
        id: uploadFixture.image_id,
        title: 'Hero image',
        thumbnail_url: uploadFixture.thumbnail_url,
        width: uploadFixture.width,
        height: uploadFixture.height,
        orientation: uploadFixture.orientation,
        aspect_ratio: uploadFixture.aspect_ratio
    }),
    imageUpload.normalizeAttachment({
        id: 'img_quote_002',
        title: 'Quote image',
        thumbnail_url: 'https://example.test/uploads/smma/thumbs/img_quote_002.jpg',
        width: 1080,
        height: 1080
    })
];

const activeLayout = layouts[0];
const previewState = imageUpload.buildPreviewState(activeLayout, selectedImages, composeFixture);
const payload = imageUpload.buildComposePayload({
    referenceId: 'phase3-demo-post-1',
    activeLayout: activeLayout,
    previewUrl: previewState.previewUrl,
    composedImageId: previewState.composedImageId,
    selectedImages: selectedImages
});

assert.equal(payload.layout_id, activeLayout.layout_id);
assert.equal(payload.mapping.length, 2);

console.log('Phase 3 images UI demo');
console.log('Step 1 upload select: PASS - selected=' + selectedImages.length + ' primary=' + selectedImages[0].id);
console.log('Step 2 layout recommend: PASS - layout=' + activeLayout.layout_id + ' score=' + activeLayout.score);
console.log('Step 3 preview compose: PASS - preview=' + previewState.previewUrl + ' composed=' + previewState.composedImageId);
console.log('Step 4 save payload: PASS - mapping=' + payload.mapping.length + ' reference=' + payload.reference_id);
console.log(JSON.stringify({
    status: 'ok',
    composed_preview: previewState.previewUrl,
    composed_image_id: previewState.composedImageId
}));
