export const imageCropper = () => `
    <form>
        <div data-controller="image-cropper"
            data-image-cropper-width-value="100"
            data-image-cropper-height-value="50"
            data-image-cropper-quality-value="0.8"
            data-image-cropper-too-small-value="The image is too small"
            data-image-cropper-unreadable-value="The image cannot be read"
            data-image-cropper-unsupported-value="WebP is not supported"
            data-image-cropper-size-text-value="%width% × %height% pixels">
            <label for="image">Image</label>
            <input id="image" type="file" accept="image/*" required
                data-image-cropper-target="source" data-action="change->image-cropper#load">
            <p role="alert" data-image-cropper-target="error" hidden></p>
            <input type="file" name="image" hidden data-image-cropper-target="output">
            <div data-image-cropper-target="editor" hidden>
                <div data-image-cropper-target="stage">
                    <div tabindex="0" data-image-cropper-target="area"
                        data-action="pointerdown->image-cropper#startMove pointermove->image-cropper#drag pointerup->image-cropper#endDrag pointercancel->image-cropper#endDrag keydown->image-cropper#nudge">
                        <span data-action="pointerdown->image-cropper#startResize"></span>
                    </div>
                </div>
                <p role="status" data-image-cropper-target="status"></p>
                <canvas data-image-cropper-target="preview"></canvas>
            </div>
        </div>
        <button type="submit">Save</button>
    </form>
`;
