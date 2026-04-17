(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const dbg = typeof window.messageEditorDebugPush === 'function'
            ? window.messageEditorDebugPush.bind(window)
            : function () {};

        const root = document.querySelector('[data-message-fabric-root]');
        if (!root || !window.fabric) {
            dbg('Editor V2: abort (sem root ou Fabric)', {
                hasRoot: Boolean(root),
                hasFabric: Boolean(window.fabric)
            });
            return;
        }

        const fabricLib = window.fabric;
        const sceneRenderer = window.MessageSceneRenderer || null;
        const layerMetaByObject = new WeakMap();
        const canvasElement = document.querySelector('[data-message-fabric-canvas]');
        const sceneInput = document.getElementById('scene_json');
        const fabricSceneInput = document.getElementById('fabric_scene_json');
        const editorEngineInput = document.getElementById('editor_engine');
        const heroInput = document.querySelector('[data-message-hero-input]');
        const heroClearButton = document.querySelector('[data-message-clear-image]');
        const currentHeroPathInput = document.getElementById('current_hero_image_path');
        const heroName = document.querySelector('[data-message-hero-name]');
        const titleInput = document.getElementById('title');
        const messageInput = document.getElementById('message');
        const linkInput = document.getElementById('link_url');
        const buttonLabelInput = document.getElementById('button_label');
        const buttonFontSizeInput = document.getElementById('button_font_size');
        const imageLinkInput = document.getElementById('image_link_url');
        const recipientModeInput = document.querySelector('[data-recipient-mode]');
        const customerRow = document.querySelector('[data-customer-row]');
        const customerSelect = document.getElementById('customer_id');
        const modeLabel = document.querySelector('[data-fabric-mode-label]');
        const editorLayersInput = document.getElementById('editor_layers_json');
        const config = window.messageEditorConfig || {};
        const bootProjectId = String(config.bootProjectId || '').trim();
        const form = root.closest('form');
        const baseCanvas = sceneRenderer && sceneRenderer.canvasDefaults
            ? sceneRenderer.canvasDefaults
            : { width: 1080, height: 1620 };
        const workspaceDefaults = {
            emptyWidth: 1080,
            emptyHeight: 1620,
            minWidth: 240,
            minHeight: 160,
            displayMaxWidth: 440,
            displayMaxHeight: 660,
            paddingX: 48,
            paddingY: 56
        };
        const canvasShell = root.querySelector('.message-fabric-editor__canvas-shell');
        let logicalCanvasSize = {
            width: baseCanvas.width,
            height: baseCanvas.height
        };

        const readBootSceneText = function () {
            const el = document.getElementById('message-editor-initial-scene');
            return el ? String(el.textContent || '').trim() : '';
        };

        const readBootLayersJson = function () {
            const el = document.getElementById('message-editor-initial-layers');
            return el ? String(el.textContent || '').trim() : '';
        };

        if (!canvasElement || !sceneInput || !fabricSceneInput || !editorEngineInput) {
            dbg('Editor V2: abort (elemento do form ausente)', {
                canvasElement: Boolean(canvasElement),
                sceneInput: Boolean(sceneInput),
                fabricSceneInput: Boolean(fabricSceneInput),
                editorEngineInput: Boolean(editorEngineInput)
            });
            return;
        }

        editorEngineInput.value = 'fabric_v2';

        dbg('Editor V2: Fabric Canvas criado', {
            hasMessageSceneRenderer: Boolean(window.MessageSceneRenderer),
            bootSceneScriptBytes: (function () {
                const el = document.getElementById('message-editor-initial-scene');
                return el ? String(el.textContent || '').length : 0;
            }()),
            bootLayersScriptBytes: (function () {
                const el = document.getElementById('message-editor-initial-layers');
                return el ? String(el.textContent || '').length : 0;
            }())
        });

        const canvas = new fabricLib.Canvas(canvasElement, {
            preserveObjectStacking: true,
            selection: true
        });

        if (typeof canvas.setDimensions === 'function') {
            canvas.setDimensions({
                width: baseCanvas.width,
                height: baseCanvas.height
            });
        } else if (typeof canvas.setWidth === 'function' && typeof canvas.setHeight === 'function') {
            canvas.setWidth(baseCanvas.width);
            canvas.setHeight(baseCanvas.height);
        }

        dbg('Editor V2: canvas inicializado', {
            width: canvasElement.width,
            height: canvasElement.height,
            devicePixelRatio: Number(window.devicePixelRatio || 1),
            editorEngineForcado: editorEngineInput.value
        });

        let heroObjectUrl = '';
        let isLoadingScene = false;
        let overlayEditor = null;
        let overlayEditingObject = null;
        let overlayEditingOriginalOpacity = null;
        let isPointerTransformActive = false;

        const clamp = function (value, min, max) {
            const numeric = Number(value);
            if (!Number.isFinite(numeric)) {
                return min;
            }

            return Math.min(Math.max(numeric, min), max);
        };

        const positiveInt = function (value, fallback) {
            const numeric = Number(value);
            if (!Number.isFinite(numeric) || numeric <= 0) {
                return fallback;
            }

            return Math.round(numeric);
        };

        const currentCanvasSize = function () {
            return {
                width: positiveInt(logicalCanvasSize.width, baseCanvas.width),
                height: positiveInt(logicalCanvasSize.height, baseCanvas.height)
            };
        };

        const emptyScene = function () {
            return {
                schemaVersion: 1,
                canvas: {
                    width: baseCanvas.width,
                    height: baseCanvas.height,
                    backgroundImage: ''
                },
                actions: {
                    primaryHrefRaw: '',
                    imageHrefRaw: ''
                },
                layers: []
            };
        };

        const sceneHasRenderableContent = function (scene) {
            if (!scene || typeof scene !== 'object') {
                return false;
            }

            const hasBackground = Boolean(
                scene.canvas
                && String(scene.canvas.backgroundImage || '').trim() !== ''
            );

            if (hasBackground) {
                return true;
            }

            return Array.isArray(scene.layers)
                && scene.layers.some(function (layer) {
                    return layer && layer.visible !== false;
                });
        };

        const clampWorkspaceSize = function (width, height) {
            return {
                width: positiveInt(
                    clamp(width, workspaceDefaults.minWidth, baseCanvas.width),
                    baseCanvas.width
                ),
                height: positiveInt(
                    clamp(height, workspaceDefaults.minHeight, baseCanvas.height),
                    baseCanvas.height
                )
            };
        };

        const isDefaultWorkspaceSize = function (width, height) {
            return positiveInt(width, baseCanvas.width) === baseCanvas.width
                && positiveInt(height, baseCanvas.height) === baseCanvas.height;
        };

        const displayViewportSize = function (width, height) {
            const logicalWidth = positiveInt(width, baseCanvas.width);
            const logicalHeight = positiveInt(height, baseCanvas.height);
            const scale = Math.min(
                workspaceDefaults.displayMaxWidth / logicalWidth,
                workspaceDefaults.displayMaxHeight / logicalHeight,
                1
            );

            return {
                width: positiveInt(Math.round(logicalWidth * scale), workspaceDefaults.displayMaxWidth),
                height: positiveInt(Math.round(logicalHeight * scale), workspaceDefaults.displayMaxHeight)
            };
        };

        const applyDisplayViewport = function (width, height) {
            const viewport = displayViewportSize(width, height);
            const wrapper = canvas.wrapperEl || (canvas.lowerCanvasEl ? canvas.lowerCanvasEl.parentElement : null);
            const widthValue = viewport.width + 'px';
            const heightValue = viewport.height + 'px';

            [wrapper, canvas.lowerCanvasEl, canvas.upperCanvasEl].forEach(function (element) {
                if (!element || !element.style) {
                    return;
                }

                element.style.width = widthValue;
                element.style.height = heightValue;
            });

            if (canvasShell) {
                canvasShell.style.setProperty('--workspace-width', widthValue);
                canvasShell.style.setProperty('--workspace-height', heightValue);
            }
        };

        const setWorkspaceDimensions = function (width, height) {
            const next = clampWorkspaceSize(width, height);
            logicalCanvasSize = {
                width: next.width,
                height: next.height
            };

            if (typeof canvas.setDimensions === 'function') {
                canvas.setDimensions({ width: next.width, height: next.height });
            } else if (typeof canvas.setWidth === 'function' && typeof canvas.setHeight === 'function') {
                canvas.setWidth(next.width);
                canvas.setHeight(next.height);
            } else {
                canvasElement.width = next.width;
                canvasElement.height = next.height;
            }

            applyDisplayViewport(next.width, next.height);

            dbg('setWorkspaceDimensions', {
                requestedWidth: next.width,
                requestedHeight: next.height,
                lowerCanvasWidth: canvas.lowerCanvasEl ? Number(canvas.lowerCanvasEl.width || 0) : null,
                lowerCanvasHeight: canvas.lowerCanvasEl ? Number(canvas.lowerCanvasEl.height || 0) : null,
                upperCanvasWidth: canvas.upperCanvasEl ? Number(canvas.upperCanvasEl.width || 0) : null,
                upperCanvasHeight: canvas.upperCanvasEl ? Number(canvas.upperCanvasEl.height || 0) : null,
                lowerCanvasStyleWidth: canvas.lowerCanvasEl ? String(canvas.lowerCanvasEl.style.width || '') : '',
                lowerCanvasStyleHeight: canvas.lowerCanvasEl ? String(canvas.lowerCanvasEl.style.height || '') : '',
                upperCanvasStyleWidth: canvas.upperCanvasEl ? String(canvas.upperCanvasEl.style.width || '') : '',
                upperCanvasStyleHeight: canvas.upperCanvasEl ? String(canvas.upperCanvasEl.style.height || '') : '',
                devicePixelRatio: Number(window.devicePixelRatio || 1)
            });
        };

        const sceneCanvasSize = function (scene) {
            return {
                width: baseCanvas.width,
                height: baseCanvas.height
            };
        };

        const collectCanvasBounds = function () {
            let bounds = null;

            canvas.getObjects().forEach(function (object) {
                if (!object || object.visible === false) {
                    return;
                }

                const rect = typeof object.getBoundingRect === 'function'
                    ? object.getBoundingRect()
                    : null;
                if (!rect) {
                    return;
                }

                const left = Math.max(0, Number(rect.left) || 0);
                const top = Math.max(0, Number(rect.top) || 0);
                const right = left + Math.max(0, Number(rect.width) || 0);
                const bottom = top + Math.max(0, Number(rect.height) || 0);

                if (!bounds) {
                    bounds = { left: left, top: top, right: right, bottom: bottom };
                    return;
                }

                bounds.left = Math.min(bounds.left, left);
                bounds.top = Math.min(bounds.top, top);
                bounds.right = Math.max(bounds.right, right);
                bounds.bottom = Math.max(bounds.bottom, bottom);
            });

            return bounds;
        };

        const targetWorkspaceFromObjects = function () {
            return {
                width: baseCanvas.width,
                height: baseCanvas.height
            };
        };

        const refreshCanvasShellState = function () {
            if (!canvasShell) {
                return;
            }

            const size = currentCanvasSize();
            const hasObjects = canvas.getObjects().length > 0;
            const hasBackground = Boolean(canvas.backgroundImage);
            const viewport = displayViewportSize(size.width, size.height);

            canvasShell.dataset.empty = hasObjects || hasBackground ? '0' : '1';
            canvasShell.style.setProperty('--workspace-width', viewport.width + 'px');
            canvasShell.style.setProperty('--workspace-height', viewport.height + 'px');
        };

        const resizeWorkspace = function (targetSize, options) {
            const opts = options && typeof options === 'object' ? options : {};
            const previous = currentCanvasSize();
            const next = clampWorkspaceSize(targetSize.width, targetSize.height);

            if (previous.width === next.width && previous.height === next.height) {
                refreshCanvasShellState();
                return;
            }

            if (opts.scaleObjects && previous.width > 0 && previous.height > 0) {
                const ratioX = next.width / previous.width;
                const ratioY = next.height / previous.height;

                canvas.getObjects().forEach(function (object) {
                    if (!object) {
                        return;
                    }

                    object.set({
                        left: Math.round((Number(object.left) || 0) * ratioX),
                        top: Math.round((Number(object.top) || 0) * ratioY),
                        scaleX: (Number(object.scaleX) || 1) * ratioX,
                        scaleY: (Number(object.scaleY) || 1) * ratioY
                    });
                    normalizeScaledObject(object);
                    if (typeof object.setCoords === 'function') {
                        object.setCoords();
                    }
                });
            }

            setWorkspaceDimensions(next.width, next.height);
            if (typeof canvas.calcOffset === 'function') {
                canvas.calcOffset();
            }
            refreshCanvasShellState();
        };

        const syncWorkspaceToObjects = function (options) {
            refreshCanvasShellState();
        };

        setWorkspaceDimensions(baseCanvas.width, baseCanvas.height);
        refreshCanvasShellState();

        const syncRecipientVisibility = function () {
            const customerMode = recipientModeInput && recipientModeInput.value === 'customer';
            if (customerRow) {
                customerRow.hidden = !customerMode;
            }
            if (!customerMode && customerSelect) {
                customerSelect.value = '';
            }
        };

        const syncHeroUi = function (label) {
            if (heroName) {
                heroName.textContent = label || 'Nenhuma imagem selecionada';
            }
            if (heroClearButton) {
                heroClearButton.hidden = !(heroObjectUrl || (currentHeroPathInput && String(currentHeroPathInput.value || '').trim() !== ''));
            }
        };

        const absoluteUrl = function (value) {
            const raw = String(value || '').trim();
            if (raw === '') {
                return '';
            }
            if (/^https?:\/\//i.test(raw) || raw.indexOf('blob:') === 0 || raw.indexOf('data:') === 0) {
                return raw;
            }
            try {
                return new URL(raw.replace(/^\/+/, ''), window.location.origin + '/').toString();
            } catch (error) {
                return raw;
            }
        };

        const isSameOriginAssetUrl = function (url) {
            const raw = String(url || '').trim();
            if (raw === '' || raw.indexOf('blob:') === 0 || raw.indexOf('data:') === 0) {
                return true;
            }
            if (!/^https?:\/\//i.test(raw)) {
                return true;
            }
            try {
                return new URL(raw).origin === window.location.origin;
            } catch (error) {
                return false;
            }
        };

        const defaultTextLayer = function (role) {
            const workspace = currentCanvasSize();
            const title = role === 'title';

            return {
                id: 'fabric_' + role + '_' + Date.now(),
                type: 'text',
                role: role,
                visible: true,
                textRaw: role === 'title' ? 'Novo titulo' : 'Novo texto',
                x: Math.round(workspace.width * (title ? 0.08 : 0.1)),
                y: Math.round(workspace.height * (title ? 0.14 : 0.42)),
                width: Math.max(180, Math.round(workspace.width * (title ? 0.78 : 0.72))),
                height: 0,
                fontFamily: 'default',
                fontSize: title
                    ? clamp(Math.round(workspace.height * 0.18), 24, 54)
                    : clamp(Math.round(workspace.height * 0.12), 18, 32),
                fontWeight: role === 'title' ? 800 : 500,
                fontStyle: 'normal',
                lineHeight: role === 'title' ? 1.08 : 1.45,
                textAlign: 'left',
                color: role === 'title' ? '#fff7f0' : '#2c1917',
                uppercase: false,
                shadow: role === 'title' ? 'strong' : 'soft'
            };
        };

        const emailButtonTextureUrl = 'https://files.manuscdn.com/user_upload_by_module/session_file/310519663565489241/ksArwtVtypUlKkEr.webp';
        let buttonTextureImage = null;
        let buttonTextureLoadPromise = null;
        let buttonTextureLoadFailed = false;

        const defaultButtonFontSize = function () {
            return 24;
        };

        const resolveButtonFontSize = function (layer) {
            const requested = Number(layer && layer.fontSize || 0);
            return clamp(Math.round(requested || defaultButtonFontSize()), 12, 96);
        };

        const buttonCanvasLabelText = function (value) {
            const raw = String(value == null ? '' : value);
            return raw.trim() !== '' ? raw : 'Ver promocoes';
        };

        const defaultButtonDimensions = function (workspace) {
            const width = Math.round((Number(workspace && workspace.width) || baseCanvas.width) * (483 / 1080));
            const height = Math.round((Number(workspace && workspace.height) || baseCanvas.height) * (156 / 1620));
            return {
                width: width,
                height: height
            };
        };

        const resolveButtonDimensions = function (layer) {
            const workspace = currentCanvasSize();
            const defaults = defaultButtonDimensions(workspace);
            const minWidth = Math.max(180, Math.round(workspace.width * 0.12));
            const maxWidth = Math.max(minWidth, workspace.width - 4);
            const minHeight = Math.max(58, Math.round(workspace.height * 0.035));
            const maxHeight = Math.max(minHeight, Math.round(workspace.height * 0.4));

            return {
                width: clamp(
                    Math.round(Number(layer && layer.width) || defaults.width),
                    minWidth,
                    maxWidth
                ),
                height: clamp(
                    Math.round(Number(layer && layer.height) || defaults.height),
                    minHeight,
                    maxHeight
                )
            };
        };

        const loadButtonTextureImage = function () {
            if (buttonTextureImage) {
                return Promise.resolve(buttonTextureImage);
            }
            if (buttonTextureLoadFailed) {
                return Promise.reject(new Error('button_texture_unavailable'));
            }
            if (buttonTextureLoadPromise) {
                return buttonTextureLoadPromise;
            }

            buttonTextureLoadPromise = new Promise(function (resolve, reject) {
                const image = new Image();
                image.crossOrigin = 'anonymous';
                image.onload = function () {
                    buttonTextureImage = image;
                    resolve(image);
                };
                image.onerror = function () {
                    buttonTextureLoadFailed = true;
                    reject(new Error('button_texture_load_failed'));
                };
                image.src = emailButtonTextureUrl;
            });

            return buttonTextureLoadPromise;
        };

        const createButtonTextureSource = function (width, height) {
            if (!buttonTextureImage || typeof document === 'undefined' || typeof document.createElement !== 'function') {
                return null;
            }

            const textureCanvas = document.createElement('canvas');
            textureCanvas.width = Math.max(1, Math.round(width));
            textureCanvas.height = Math.max(1, Math.round(height));
            const ctx = textureCanvas.getContext('2d');
            if (!ctx) {
                return null;
            }

            ctx.drawImage(buttonTextureImage, 0, 0, textureCanvas.width, textureCanvas.height);
            return textureCanvas;
        };

        const applyButtonTexture = function (rect, width, height) {
            if (!rect) {
                return;
            }

            const fallbackFill = '#b36b3d';
            if (typeof fabricLib.Pattern !== 'function') {
                rect.set('fill', fallbackFill);
                return;
            }

            const textureSource = createButtonTextureSource(width, height);
            if (textureSource) {
                rect.set('fill', new fabricLib.Pattern({
                    source: textureSource,
                    repeat: 'no-repeat'
                }));
                rect.dirty = true;
                canvas.requestRenderAll();
                return;
            }

            rect.set('fill', fallbackFill);
            rect.dirty = true;
            loadButtonTextureImage()
                .then(function () {
                    applyButtonTexture(rect, width, height);
                })
                .catch(function () {
                    buttonTextureLoadFailed = true;
                    rect.set('fill', fallbackFill);
                    rect.dirty = true;
                    canvas.requestRenderAll();
                });
        };

        const defaultButtonLayer = function () {
            const workspace = currentCanvasSize();
            const dimensions = defaultButtonDimensions(workspace);
            const width = resolveButtonDimensions({ width: dimensions.width, height: dimensions.height }).width;
            const height = resolveButtonDimensions({ width: dimensions.width, height: dimensions.height }).height;

            return {
                id: 'fabric_button_' + Date.now(),
                type: 'button',
                role: 'button',
                visible: true,
                textRaw: 'Ver promocoes',
                hrefRaw: '',
                x: Math.round((workspace.width - width) / 2),
                y: Math.round(Math.max(20, workspace.height - height - (workspace.height * 0.14))),
                width: width,
                height: height,
                fontSize: defaultButtonFontSize()
            };
        };

        const hotspotPhotoRectForCanvas = function (width, height) {
            const canvasWidth = positiveInt(width, baseCanvas.width);
            const canvasHeight = positiveInt(height, baseCanvas.height);
            const rectWidth = clamp(Math.round(canvasWidth * 0.34), 180, 420);
            const rectHeight = clamp(Math.round(canvasHeight * 0.32), 220, 620);

            return {
                x: Math.round((canvasWidth - rectWidth) / 2),
                y: Math.round(canvasHeight * 0.47),
                width: rectWidth,
                height: rectHeight
            };
        };

        const defaultHotspotLayer = function () {
            const workspace = currentCanvasSize();
            const rect = hotspotPhotoRectForCanvas(workspace.width, workspace.height);

            return {
                id: 'fabric_hotspot_' + Date.now(),
                type: 'hotspot',
                role: 'image_hotspot',
                visible: true,
                textRaw: '',
                hrefRaw: '',
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height
            };
        };

        const shouldMigrateHotspotToPhotoArea = function (layer) {
            if (!layer || String(layer.type || '') !== 'hotspot') {
                return false;
            }

            const role = String(layer.role || '');
            const hrefRaw = String(layer.hrefRaw || '').trim();
            const y = Number(layer.y || 0);
            const height = Number(layer.height || 0);
            const width = Number(layer.width || 0);

            if (hrefRaw === '' || (role !== '' && role !== 'image_hotspot')) {
                return false;
            }

            return y >= Math.round(baseCanvas.height * 0.68)
                && height <= Math.round(baseCanvas.height * 0.18)
                && width <= Math.round(baseCanvas.width * 0.42);
        };

        const migrateSceneHotspotsToPhotoArea = function (scene) {
            if (!scene || !Array.isArray(scene.layers)) {
                return scene;
            }

            let migrated = 0;
            const rect = hotspotPhotoRectForCanvas(baseCanvas.width, baseCanvas.height);
            const nextScene = Object.assign({}, scene, {
                layers: scene.layers.map(function (layer) {
                    if (!shouldMigrateHotspotToPhotoArea(layer)) {
                        return layer;
                    }

                    migrated += 1;

                    return Object.assign({}, layer, {
                        x: rect.x,
                        y: rect.y,
                        width: rect.width,
                        height: rect.height
                    });
                })
            });

            if (migrated > 0) {
                dbg('migrateSceneHotspotsToPhotoArea', {
                    migratedCount: migrated,
                    targetX: rect.x,
                    targetY: rect.y,
                    targetWidth: rect.width,
                    targetHeight: rect.height
                });
            }

            return nextScene;
        };

        const objectShadow = function (layer) {
            if (layer.shadow === 'strong') {
                return new fabricLib.Shadow({
                    color: 'rgba(0, 0, 0, 0.32)',
                    blur: 16,
                    offsetX: 0,
                    offsetY: 6
                });
            }

            if (layer.shadow === 'soft') {
                return new fabricLib.Shadow({
                    color: 'rgba(0, 0, 0, 0.18)',
                    blur: 10,
                    offsetX: 0,
                    offsetY: 3
                });
            }

            return null;
        };

        const objectRawText = function (layer) {
            return String(layer && layer.textRaw ? layer.textRaw : '');
        };

        const normalizeLayerTextContent = function (value, preserveParagraphs) {
            const keepParagraphs = preserveParagraphs !== false;
            let normalized = String(value == null ? '' : value)
                .replace(/\r\n?/g, '\n')
                .replace(/\u00a0/g, ' ');

            if (keepParagraphs) {
                normalized = normalized.replace(/\n{3,}/g, '\n\n');
            } else {
                normalized = normalized.replace(/\n+/g, ' ');
            }

            return normalized.trim() === '' ? '' : normalized;
        };

        const visibleTextForEditor = function (layer) {
            const preserveParagraphs = String(layer && layer.role || '') !== 'title';
            const normalized = normalizeLayerTextContent(
                objectRawText(layer),
                preserveParagraphs
            );

            if (
                sceneRenderer
                && typeof sceneRenderer.resolvePreviewText === 'function'
            ) {
                return normalizeLayerTextContent(
                    sceneRenderer.resolvePreviewText(normalized),
                    preserveParagraphs
                );
            }

            return normalized;
        };

        const currentHeroUrl = function () {
            if (heroObjectUrl !== '') {
                return heroObjectUrl;
            }

            if (currentHeroPathInput && String(currentHeroPathInput.value || '').trim() !== '') {
                return absoluteUrl(currentHeroPathInput.value);
            }

            if (config.currentHeroImageUrl) {
                return absoluteUrl(config.currentHeroImageUrl);
            }

            return '';
        };

        const clearEditorFormStateForEmptyBoot = function () {
            if (heroObjectUrl) {
                try {
                    URL.revokeObjectURL(heroObjectUrl);
                } catch (error) {
                    /* noop */
                }
                heroObjectUrl = '';
            }

            if (heroInput) {
                heroInput.value = '';
            }

            if (currentHeroPathInput) {
                currentHeroPathInput.value = '';
            }

            if (titleInput) {
                titleInput.value = '';
            }

            if (messageInput) {
                messageInput.value = '';
            }

            if (linkInput) {
                linkInput.value = '';
            }

            if (buttonLabelInput) {
                buttonLabelInput.value = '';
            }

            if (imageLinkInput) {
                imageLinkInput.value = '';
            }

            if (editorLayersInput) {
                editorLayersInput.value = '[]';
            }

            setInputValue('show_title', '0');
            setInputValue('show_body', '0');
            setInputValue('show_button', '0');
            setInputValue('show_image_hotspot', '0');
            syncHeroUi('');
        };

        const setModeLabel = function () {
            if (!modeLabel) {
                return;
            }

            modeLabel.textContent = 'V2 ativa para envio';
        };

        const setControlCursor = function (object, controlName, cursor) {
            if (!object || !object.controls || !object.controls[controlName]) {
                return;
            }

            object.controls[controlName].cursorStyle = cursor;
            object.controls[controlName].cursorStyleHandler = function () {
                return cursor;
            };
        };

        const configureObjectControlCursors = function (object) {
            if (!object || !object.controls) {
                return;
            }

            setControlCursor(object, 'mt', 'ns-resize');
            setControlCursor(object, 'mb', 'ns-resize');
            setControlCursor(object, 'ml', 'ew-resize');
            setControlCursor(object, 'mr', 'ew-resize');
            setControlCursor(object, 'tl', 'nwse-resize');
            setControlCursor(object, 'br', 'nwse-resize');
            setControlCursor(object, 'tr', 'nesw-resize');
            setControlCursor(object, 'bl', 'nesw-resize');
        };

        const markObject = function (object, layer) {
            const meta = {
                layerId: String(layer.id || ('layer_' + Date.now())),
                layerType: String(layer.type || 'text'),
                layerRole: String(layer.role || ''),
                textRaw: objectRawText(layer),
                hrefRaw: String(layer.hrefRaw || ''),
                width: Math.max(0, Math.round(Number(layer.width) || 0)),
                height: Math.max(0, Math.round(Number(layer.height) || 0)),
                fontSize: Math.max(12, Math.round(Number(layer.fontSize) || 18)),
                uppercase: Boolean(layer.uppercase),
                shadow: String(layer.shadow || 'off'),
                textAlign: String(layer.textAlign || 'left'),
                visible: layer.visible !== false
            };
            layerMetaByObject.set(object, meta);
            if (typeof object.set === 'function') {
                try {
                    object.set('data', meta);
                } catch (err) {
                    object.data = meta;
                }
            } else {
                object.data = meta;
            }

            if ('hasRotatingPoint' in object) {
                object.hasRotatingPoint = false;
            }
            object.cornerColor = '#ffd15c';
            object.cornerStrokeColor = '#1d1418';
            object.borderColor = 'rgba(255, 197, 89, 0.95)';
            object.transparentCorners = false;
            object.cornerStyle = 'circle';
            object.padding = 0;
            configureObjectControlCursors(object);

            if (String(meta.layerType || '') === 'hotspot') {
                object.cornerSize = 10;
                object.lockRotation = true;
                object.hasBorders = false;
                if (typeof object.setControlsVisibility === 'function') {
                    object.setControlsVisibility({
                        mtr: false,
                        mt: true,
                        mb: true,
                        ml: true,
                        mr: true
                    });
                }
            } else if (typeof object.setControlsVisibility === 'function') {
                object.setControlsVisibility({ mtr: false });
            }

            return object;
        };

        const createTextObject = function (layer) {
            const text = new fabricLib.Textbox(visibleTextForEditor(layer), {
                left: clamp(layer.x, 0, baseCanvas.width),
                top: clamp(layer.y, 0, baseCanvas.height),
                originX: 'left',
                originY: 'top',
                width: Math.max(80, layer.width || 80),
                fill: layer.color || '#ffffff',
                fontFamily: 'Arial, Helvetica, sans-serif',
                fontSize: Math.max(12, layer.fontSize || 18),
                fontWeight: Number(layer.fontWeight || 400) >= 700 ? 800 : 400,
                fontStyle: layer.fontStyle === 'italic' ? 'italic' : 'normal',
                lineHeight: clamp(layer.lineHeight || 1.4, 0.7, 4),
                textAlign: ['left', 'center', 'right'].includes(layer.textAlign) ? layer.textAlign : 'left',
                editable: false,
                lockRotation: true,
                objectCaching: false
            });

            const shadow = objectShadow(layer);
            if (shadow) {
                text.shadow = shadow;
            }

            return markObject(text, layer);
        };

        const createButtonObject = function (layer) {
            const dimensions = resolveButtonDimensions(layer);
            const width = dimensions.width;
            const height = dimensions.height;
            const fontSize = resolveButtonFontSize(layer);
            const rect = new fabricLib.Rect({
                left: 0,
                top: 0,
                rx: Math.round(height / 2),
                ry: Math.round(height / 2),
                width: width,
                height: height,
                fill: '#b36b3d',
                strokeWidth: 0,
                shadow: new fabricLib.Shadow({
                    color: 'rgba(93, 49, 27, 0.22)',
                    blur: 14,
                    offsetX: 0,
                    offsetY: 6
                }),
                objectCaching: false,
                evented: false,
                selectable: false
            });
            applyButtonTexture(rect, width, height);
            const label = new fabricLib.Text(buttonCanvasLabelText(layer.textRaw), {
                left: Math.round(width / 2),
                top: Math.round(height / 2),
                originX: 'center',
                originY: 'center',
                fontFamily: 'Helvetica Neue, Arial, sans-serif',
                fontSize: fontSize,
                fontWeight: '700',
                fill: '#ffffff',
                textAlign: 'center',
                editable: false,
                objectCaching: false,
                evented: false,
                selectable: false
            });

            const group = new fabricLib.Group([rect, label], {
                left: clamp(layer.x, 0, baseCanvas.width),
                top: clamp(layer.y, 0, baseCanvas.height),
                originX: 'left',
                originY: 'top',
                objectCaching: false,
                lockRotation: true,
                subTargetCheck: false,
                centeredScaling: false
            });

            return markObject(group, layer);
        };

        const createHotspotObject = function (layer) {
            const width = Math.max(80, layer.width || 120);
            const height = Math.max(50, layer.height || 70);
            const hotspot = new fabricLib.Rect({
                left: clamp(layer.x, 0, baseCanvas.width),
                top: clamp(layer.y, 0, baseCanvas.height),
                originX: 'left',
                originY: 'top',
                width: width,
                height: height,
                fill: 'rgba(255, 209, 92, 0.10)',
                stroke: '#ffd15c',
                strokeDashArray: [14, 8],
                strokeWidth: 2,
                strokeUniform: true,
                objectCaching: false
            });

            return markObject(hotspot, layer);
        };

        const fabricObjectText = function (object) {
            if (!object) {
                return '';
            }
            const direct = object.text;
            if (typeof direct === 'string' && direct !== '') {
                return direct;
            }
            const legacy = object._text;
            if (typeof legacy === 'string') {
                return legacy;
            }
            return '';
        };

        const objectTopLeft = function (object) {
            if (!object) {
                return { x: 0, y: 0 };
            }

            if (typeof object.getPointByOrigin === 'function') {
                try {
                    const point = object.getPointByOrigin('left', 'top');
                    if (point) {
                        return {
                            x: Math.round(Number(point.x) || 0),
                            y: Math.round(Number(point.y) || 0)
                        };
                    }
                } catch (err) {
                    // segue para fallback
                }
            }

            if (typeof object.getBoundingRect === 'function') {
                const rect = object.getBoundingRect();
                if (rect) {
                    return {
                        x: Math.round(Number(rect.left) || 0),
                        y: Math.round(Number(rect.top) || 0)
                    };
                }
            }

            return {
                x: Math.round(Number(object.left) || 0),
                y: Math.round(Number(object.top) || 0)
            };
        };

        const objectScaledSize = function (object) {
            if (!object) {
                return { width: 0, height: 0 };
            }

            const width = typeof object.getScaledWidth === 'function'
                ? Number(object.getScaledWidth() || 0)
                : Number(object.width || 0) * Number(object.scaleX || 1);
            const height = typeof object.getScaledHeight === 'function'
                ? Number(object.getScaledHeight() || 0)
                : Number(object.height || 0) * Number(object.scaleY || 1);

            return {
                width: Math.round(width || 0),
                height: Math.round(height || 0)
            };
        };

        const objectToSceneLayer = function (object) {
            const meta = object ? (layerMetaByObject.get(object) || object.data) : null;
            if (!object || !meta) {
                return null;
            }

            const layerType = String(meta.layerType || '');
            const topLeft = objectTopLeft(object);
            const size = objectScaledSize(object);

            dbg('objectToSceneLayer', {
                id: String(meta.layerId || ''),
                type: layerType,
                objectLeft: Number(object.left || 0),
                objectTop: Number(object.top || 0),
                originX: String(object.originX || ''),
                originY: String(object.originY || ''),
                serializedX: topLeft.x,
                serializedY: topLeft.y,
                serializedWidth: size.width,
                serializedHeight: size.height
            });

            if (layerType === 'text') {
                return {
                    id: String(meta.layerId || ('layer_' + Date.now())),
                    type: 'text',
                    role: String(meta.layerRole || 'body'),
                    visible: object.visible !== false,
                    textRaw: normalizeLayerTextContent(
                        String(meta.textRaw || fabricObjectText(object) || ''),
                        String(meta.layerRole || 'body') !== 'title'
                    ),
                    hrefRaw: '',
                    x: topLeft.x,
                    y: topLeft.y,
                    width: size.width,
                    height: size.height,
                    fontFamily: 'default',
                    fontSize: Math.round((object.fontSize || 18) * (object.scaleY || 1)),
                    fontWeight: String(object.fontWeight || '400') === '800' || Number(object.fontWeight) >= 700 ? 800 : 400,
                    fontStyle: object.fontStyle === 'italic' ? 'italic' : 'normal',
                    lineHeight: clamp(object.lineHeight || 1.4, 0.7, 4),
                    textAlign: ['left', 'center', 'right'].includes(object.textAlign) ? object.textAlign : 'left',
                    color: object.fill || '#ffffff',
                    uppercase: Boolean(meta.uppercase),
                    shadow: String(meta.shadow || 'off')
                };
            }

            if (layerType === 'button') {
                const rectObject = Array.isArray(object._objects)
                    ? object._objects.find(function (item) { return item.type === 'rect'; })
                    : null;
                const labelObject = Array.isArray(object._objects)
                    ? object._objects.find(function (item) { return item.type === 'textbox' || item.type === 'i-text' || item.type === 'text'; })
                    : null;
                const buttonDimensions = resolveButtonDimensions({
                    width: Number(meta.width) || Number(rectObject && rectObject.width) || size.width,
                    height: Number(meta.height) || Number(rectObject && rectObject.height) || size.height
                });

                return {
                    id: String(meta.layerId || ('layer_' + Date.now())),
                    type: 'button',
                    role: 'button',
                    visible: object.visible !== false,
                    textRaw: String(
                        typeof meta.textRaw === 'string'
                            ? meta.textRaw
                            : ((labelObject && fabricObjectText(labelObject)) || 'Ver promocoes')
                    ),
                    hrefRaw: String(meta.hrefRaw || ''),
                    x: topLeft.x,
                    y: topLeft.y,
                    width: buttonDimensions.width,
                    height: buttonDimensions.height,
                    fontSize: Math.max(
                        12,
                        Math.round(
                            Number(
                                meta.fontSize
                                || (labelObject && labelObject.fontSize)
                                || defaultButtonFontSize()
                            ) || defaultButtonFontSize()
                        )
                    )
                };
            }

            if (layerType === 'hotspot') {
                return {
                    id: String(meta.layerId || ('layer_' + Date.now())),
                    type: 'hotspot',
                    role: 'image_hotspot',
                    visible: object.visible !== false,
                    textRaw: '',
                    hrefRaw: String(meta.hrefRaw || ''),
                    x: topLeft.x,
                    y: topLeft.y,
                    width: size.width,
                    height: size.height
                };
            }

            return null;
        };

        const sceneToObjects = function (scene) {
            const layers = Array.isArray(scene.layers) ? scene.layers : [];
            return layers.map(function (layer, index) {
                try {
                    if (layer.type === 'button') {
                        return createButtonObject(layer);
                    }

                    if (layer.type === 'hotspot') {
                        return createHotspotObject(layer);
                    }

                    return createTextObject(layer);
                } catch (error) {
                    dbg('sceneToObjects: layer_error', {
                        index: index,
                        id: layer && layer.id ? String(layer.id) : '',
                        type: layer && layer.type ? String(layer.type) : '',
                        message: String(error && error.message ? error.message : error)
                    });
                    return null;
                }
            });
        };

        const syncButtonGroupText = function (group) {
            const gMeta = group ? (layerMetaByObject.get(group) || group.data) : null;
            if (!group || String(gMeta && gMeta.layerType || '') !== 'button' || !Array.isArray(group._objects)) {
                return;
            }

            const rect = group._objects[0];
            const label = group._objects[1];
            if (!rect || !label) {
                return;
            }

            const anchor = objectTopLeft(group);
            const dimensions = resolveButtonDimensions({
                width: Math.round((Number(gMeta.width) || Number(rect.width) || 0) * Number(group.scaleX || 1)),
                height: Math.round((Number(gMeta.height) || Number(rect.height) || 0) * Number(group.scaleY || 1))
            });
            const width = dimensions.width;
            const height = dimensions.height;
            const fontSize = resolveButtonFontSize(gMeta);

            group.set({
                scaleX: 1,
                scaleY: 1
            });
            rect.set({
                width: width,
                height: height,
                rx: Math.round(height / 2),
                ry: Math.round(height / 2),
                strokeWidth: 0
            });
            applyButtonTexture(rect, width, height);
            label.set({
                text: buttonCanvasLabelText(gMeta.textRaw),
                left: Math.round(width / 2),
                top: Math.round(height / 2),
                originX: 'center',
                originY: 'center',
                fontFamily: 'Helvetica Neue, Arial, sans-serif',
                fill: '#ffffff',
                textAlign: 'center',
                fontSize: fontSize
            });
            gMeta.width = width;
            gMeta.height = height;
            gMeta.fontSize = Math.max(12, Math.round(Number(fontSize) || defaultButtonFontSize()));
            if (group.data && typeof group.data === 'object') {
                group.data.width = gMeta.width;
                group.data.height = gMeta.height;
                group.data.fontSize = gMeta.fontSize;
            }
            if (typeof group.addWithUpdate === 'function') {
                group.addWithUpdate();
            }
            group.set({
                left: anchor.x,
                top: anchor.y,
                originX: 'left',
                originY: 'top'
            });
            if (typeof group.setCoords === 'function') {
                group.setCoords();
            }
        };

        const syncHotspotAppearance = function (object) {
            const meta = object ? (layerMetaByObject.get(object) || object.data) : null;
            if (!object || String(meta && meta.layerType || '') !== 'hotspot') {
                return;
            }

            const width = Math.max(80, object.getScaledWidth());
            const height = Math.max(50, object.getScaledHeight());

            object.set({
                width: width,
                height: height,
                scaleX: 1,
                scaleY: 1
            });
        };

        const normalizeScaledObject = function (object) {
            const meta = object ? (layerMetaByObject.get(object) || object.data) : null;
            if (!object || !meta) {
                return;
            }

            if (String(meta.layerType || '') === 'text') {
                const width = Math.max(80, object.getScaledWidth());
                const fontSize = Math.max(12, Math.round((object.fontSize || 18) * (object.scaleY || 1)));
                object.set({
                    width: width,
                    fontSize: fontSize,
                    scaleX: 1,
                    scaleY: 1
                });
                object.setCoords();
                return;
            }

            if (String(meta.layerType || '') === 'button') {
                syncButtonGroupText(object);
                object.setCoords();
                return;
            }

            if (String(meta.layerType || '') === 'hotspot') {
                syncHotspotAppearance(object);
                object.setCoords();
            }
        };

        const buildFallbackSceneFromForm = function () {
            const workspace = currentCanvasSize();
            const fallbackScene = {
                schemaVersion: 1,
                canvas: {
                    width: workspace.width,
                    height: workspace.height,
                    backgroundImage: currentHeroUrl()
                },
                actions: {
                    primaryHrefRaw: String(linkInput && linkInput.value ? linkInput.value : ''),
                    imageHrefRaw: String(imageLinkInput && imageLinkInput.value ? imageLinkInput.value : '')
                },
                layers: []
            };

            if (titleInput && String(titleInput.value || '').trim() !== '') {
                const titleLayer = defaultTextLayer('title');
                titleLayer.textRaw = normalizeLayerTextContent(titleInput.value || '', false);
                titleLayer.fontStyle = 'italic';
                titleLayer.fontWeight = 800;
                fallbackScene.layers.push(titleLayer);
            }

            if (messageInput && String(messageInput.value || '').trim() !== '') {
                const bodyLayer = defaultTextLayer('body');
                bodyLayer.textRaw = normalizeLayerTextContent(messageInput.value || '', true);
                bodyLayer.fontStyle = 'italic';
                bodyLayer.fontWeight = 700;
                bodyLayer.shadow = 'off';
                bodyLayer.x = 110;
                bodyLayer.y = 520;
                bodyLayer.width = 590;
                fallbackScene.layers.push(bodyLayer);
            }

            const rawButtonLabel = String(buttonLabelInput && typeof buttonLabelInput.value !== 'undefined' ? buttonLabelInput.value : '');
            if ((buttonLabelInput && rawButtonLabel.trim() !== '') || (linkInput && String(linkInput.value || '').trim() !== '')) {
                const buttonLayer = defaultButtonLayer();
                buttonLayer.textRaw = rawButtonLabel;
                buttonLayer.hrefRaw = String(linkInput && linkInput.value ? linkInput.value : '').trim();
                buttonLayer.fontSize = Math.max(12, Math.round(Number(buttonFontSizeInput && buttonFontSizeInput.value ? buttonFontSizeInput.value : buttonLayer.fontSize) || buttonLayer.fontSize || 18));
                fallbackScene.layers.push(buttonLayer);
            }

            if (imageLinkInput && String(imageLinkInput.value || '').trim() !== '') {
                const hotspotLayer = defaultHotspotLayer();
                hotspotLayer.hrefRaw = String(imageLinkInput.value || '').trim();
                hotspotLayer.visible = false;
                fallbackScene.layers.push(hotspotLayer);
            }

            return sceneRenderer && typeof sceneRenderer.normalizeScene === 'function'
                ? sceneRenderer.normalizeScene(fallbackScene)
                : fallbackScene;
        };

        const normalizeSceneToBaseCanvas = function (scene) {
            const source = scene && typeof scene === 'object'
                ? scene
                : {
                    schemaVersion: 1,
                    canvas: { width: baseCanvas.width, height: baseCanvas.height, backgroundImage: '' },
                    layers: [],
                    actions: {}
                };
            const sourceCanvas = source.canvas && typeof source.canvas === 'object'
                ? source.canvas
                : {};
            const sourceWidth = positiveInt(sourceCanvas.width, baseCanvas.width);
            const sourceHeight = positiveInt(sourceCanvas.height, baseCanvas.height);
            const ratioX = baseCanvas.width / Math.max(1, sourceWidth);
            const ratioY = baseCanvas.height / Math.max(1, sourceHeight);
            const fontScale = (ratioX + ratioY) / 2;
            const needsRescale = Math.abs(sourceWidth - baseCanvas.width) > 2
                || Math.abs(sourceHeight - baseCanvas.height) > 2;

            return {
                schemaVersion: Number(source.schemaVersion || 1) || 1,
                canvas: {
                    width: baseCanvas.width,
                    height: baseCanvas.height,
                    backgroundImage: String(sourceCanvas.backgroundImage || '')
                },
                actions: source.actions && typeof source.actions === 'object'
                    ? source.actions
                    : {},
                layers: (Array.isArray(source.layers) ? source.layers : []).map(function (layer) {
                    if (!layer || typeof layer !== 'object') {
                        return layer;
                    }

                    if (!needsRescale) {
                        return Object.assign({}, layer, {
                            x: Math.round(Number(layer.x) || 0),
                            y: Math.round(Number(layer.y) || 0),
                            width: Math.round(Number(layer.width) || 0),
                            height: Math.round(Number(layer.height) || 0)
                        });
                    }

                    return Object.assign({}, layer, {
                        x: Math.round((Number(layer.x) || 0) * ratioX),
                        y: Math.round((Number(layer.y) || 0) * ratioY),
                        width: Math.round((Number(layer.width) || 0) * ratioX),
                        height: Math.round((Number(layer.height) || 0) * ratioY),
                        fontSize: typeof layer.fontSize === 'undefined'
                            ? layer.fontSize
                            : Math.max(10, Math.round((Number(layer.fontSize) || 18) * fontScale))
                    });
                })
            };
        };

        const buildSceneFromLegacyEditorLayersJson = function () {
            let raw = readBootLayersJson();
            if (raw === '') {
                const el = document.getElementById('editor_layers_json');
                raw = el ? String(el.value || '').trim() : '';
            }
            if (raw === '') {
                return null;
            }

            let items;
            try {
                items = JSON.parse(raw);
            } catch (err) {
                return null;
            }

            if (!Array.isArray(items) || items.length === 0) {
                return null;
            }

            const W = baseCanvas.width;
            const H = baseCanvas.height;
            const pct = function (value, axisSize) {
                const n = Number(value);
                if (!Number.isFinite(n)) {
                    return 0;
                }
                const p = Math.max(0, Math.min(100, n));
                return Math.round((p / 100) * axisSize);
            };

            const titleColorEl = document.getElementById('title_color');
            const bodyColorEl = document.getElementById('body_color');
            const titleColor = titleColorEl && titleColorEl.value ? String(titleColorEl.value) : '#fff7f0';
            const bodyColor = bodyColorEl && bodyColorEl.value ? String(bodyColorEl.value) : '#2c1917';

            const layers = [];

            items.forEach(function (item, index) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                const type = String(item.type || '');

                if (type === 'title' || type === 'body') {
                    const content = normalizeLayerTextContent(item.content || '', type !== 'title');
                    if (content === '') {
                        return;
                    }

                    layers.push({
                        id: String(item.id || ('legacy_' + index)),
                        type: 'text',
                        role: type,
                        visible: true,
                        textRaw: content,
                        hrefRaw: '',
                        x: pct(item.x, W),
                        y: pct(item.y, H),
                        width: Math.max(80, pct(item.width, W) || 240),
                        height: Math.max(0, pct(item.height, H)),
                        fontFamily: 'default',
                        fontSize: Math.max(12, Math.min(200, Number(item.font_size) || 18)),
                        fontWeight: item.bold ? 700 : 400,
                        fontStyle: item.italic ? 'italic' : 'normal',
                        lineHeight: Math.max(0.6, Math.min(4, (Number(item.line_height) || 140) / 100)),
                        textAlign: ['left', 'center', 'right'].includes(String(item.align || ''))
                            ? String(item.align)
                            : 'left',
                        color: type === 'title' ? titleColor : bodyColor,
                        uppercase: Boolean(item.uppercase),
                        shadow: ['off', 'soft', 'strong'].includes(String(item.shadow || ''))
                            ? String(item.shadow)
                            : 'off'
                    });
                    return;
                }

                if (type === 'button') {
                    const content = String(item.content || item.label || '').trim();
                    if (content === '') {
                        return;
                    }

                    layers.push({
                        id: String(item.id || ('legacy_btn_' + index)),
                        type: 'button',
                        role: 'button',
                        visible: true,
                        textRaw: content,
                        hrefRaw: String(item.link_url || '').trim(),
                        x: pct(item.x, W),
                        y: pct(item.y, H),
                        width: Math.max(140, pct(item.width, W) || 200),
                        height: Math.max(50, pct(item.height, H) || 70),
                        fontFamily: 'default',
                        fontSize: Math.max(12, Math.min(200, Number(item.font_size) || 18)),
                        lineHeight: 1.2,
                        fontWeight: 700,
                        fontStyle: 'normal',
                        textAlign: 'center',
                        color: '#ffffff',
                        uppercase: false,
                        shadow: 'off'
                    });
                    return;
                }

                if (type === 'hotspot') {
                    const href = String(item.link_url || '').trim();
                    if (href === '') {
                        return;
                    }

                    layers.push({
                        id: String(item.id || ('legacy_hs_' + index)),
                        type: 'hotspot',
                        role: 'image_hotspot',
                        visible: true,
                        textRaw: '',
                        hrefRaw: href,
                        x: pct(item.x, W),
                        y: pct(item.y, H),
                        width: Math.max(80, pct(item.width, W) || 120),
                        height: Math.max(50, pct(item.height, H) || 70),
                        fontFamily: 'default',
                        fontSize: 18,
                        lineHeight: 1.2,
                        fontWeight: 400,
                        fontStyle: 'normal',
                        textAlign: 'left',
                        color: '#ffffff',
                        uppercase: false,
                        shadow: 'off'
                    });
                }
            });

            if (layers.length === 0) {
                return null;
            }

            const wrapper = {
                schemaVersion: 1,
                canvas: {
                    width: W,
                    height: H,
                    backgroundImage: currentHeroUrl() || ''
                },
                actions: {
                    primaryHrefRaw: linkInput ? String(linkInput.value || '').trim() : '',
                    imageHrefRaw: imageLinkInput ? String(imageLinkInput.value || '').trim() : ''
                },
                layers: layers
            };

            return sceneRenderer && typeof sceneRenderer.normalizeScene === 'function'
                ? sceneRenderer.normalizeScene(wrapper)
                : wrapper;
        };

        const sceneLayersByRole = function (scene) {
            const index = {};
            const layers = scene && Array.isArray(scene.layers) ? scene.layers : [];

            layers.forEach(function (layer) {
                if (!layer || typeof layer !== 'object') {
                    return;
                }

                let role = String(layer.role || '').trim();
                if (role === '') {
                    role = String(layer.type === 'hotspot' ? 'image_hotspot' : (layer.type || '')).trim();
                }

                if (role === '' || Object.prototype.hasOwnProperty.call(index, role)) {
                    return;
                }

                index[role] = layer;
            });

            return index;
        };

        const scenePercentDelta = function (valueA, axisA, valueB, axisB) {
            if (!axisA || !axisB) {
                return 0;
            }

            return Math.abs(((Number(valueA) || 0) / axisA) * 100 - (((Number(valueB) || 0) / axisB) * 100));
        };

        const sceneLayerOverflowsCanvas = function (layer, canvasInfo) {
            const canvasWidth = Number(canvasInfo && canvasInfo.width || 0);
            const canvasHeight = Number(canvasInfo && canvasInfo.height || 0);
            const x = Number(layer && layer.x || 0);
            const y = Number(layer && layer.y || 0);
            const width = Number(layer && layer.width || 0);
            const height = Number(layer && layer.height || 0);
            const overflowAllowance = String(layer && layer.type || '') === 'text' ? 24 : 8;

            if (canvasWidth > 0 && width > 0 && (x + width) > (canvasWidth + overflowAllowance)) {
                return true;
            }

            if (canvasHeight > 0 && height > 0 && (y + height) > (canvasHeight + overflowAllowance)) {
                return true;
            }

            return false;
        };

        const shouldPreferLegacyScene = function (scene, legacyScene) {
            if (!scene || !legacyScene) {
                return false;
            }

            const sceneLayers = sceneLayersByRole(scene);
            const legacyLayers = sceneLayersByRole(legacyScene);
            const sceneCanvas = scene && scene.canvas ? scene.canvas : {};
            const sceneCanvasWidth = Number(sceneCanvas.width || 0);
            const sceneCanvasHeight = Number(sceneCanvas.height || 0);
            const sceneUsesCustomCanvas = Math.abs(sceneCanvasWidth - baseCanvas.width) > 2
                || Math.abs(sceneCanvasHeight - baseCanvas.height) > 2;
            const roles = ['title', 'body', 'button', 'image_hotspot'];
            let compared = 0;
            let strongMismatch = false;

            if (sceneUsesCustomCanvas && sceneCanvasWidth > 0 && sceneCanvasHeight > 0) {
                const sceneHasOverflow = Object.keys(sceneLayers).some(function (role) {
                    return sceneLayerOverflowsCanvas(sceneLayers[role], sceneCanvas);
                });

                if (!sceneHasOverflow) {
                    return false;
                }
            }

            roles.forEach(function (role) {
                if (!sceneLayers[role] || !legacyLayers[role]) {
                    return;
                }

                compared += 1;

                if (
                    sceneLayerOverflowsCanvas(sceneLayers[role], scene.canvas || {})
                    && !sceneLayerOverflowsCanvas(legacyLayers[role], legacyScene.canvas || {})
                ) {
                    strongMismatch = true;
                    return;
                }

                const xDelta = scenePercentDelta(
                    sceneLayers[role].x,
                    Number(scene && scene.canvas && scene.canvas.width || 0),
                    legacyLayers[role].x,
                    Number(legacyScene && legacyScene.canvas && legacyScene.canvas.width || 0)
                );
                const yDelta = scenePercentDelta(
                    sceneLayers[role].y,
                    Number(scene && scene.canvas && scene.canvas.height || 0),
                    legacyLayers[role].y,
                    Number(legacyScene && legacyScene.canvas && legacyScene.canvas.height || 0)
                );
                const widthDelta = scenePercentDelta(
                    sceneLayers[role].width,
                    Number(scene && scene.canvas && scene.canvas.width || 0),
                    legacyLayers[role].width,
                    Number(legacyScene && legacyScene.canvas && legacyScene.canvas.width || 0)
                );
                const heightDelta = scenePercentDelta(
                    sceneLayers[role].height,
                    Math.max(1, Number(scene && scene.canvas && scene.canvas.height || 0)),
                    legacyLayers[role].height,
                    Math.max(1, Number(legacyScene && legacyScene.canvas && legacyScene.canvas.height || 0))
                );

                if (xDelta >= 18 || yDelta >= 18 || widthDelta >= 22 || heightDelta >= 22) {
                    strongMismatch = true;
                }
            });

            return compared > 0 && strongMismatch;
        };

        const setInputValue = function (id, value) {
            const input = document.getElementById(id);
            if (input) {
                input.value = String(value == null ? '' : value);
            }
        };

        const percentFromPx = function (value, axisSize) {
            const axis = Math.max(1, Number(axisSize) || 0);
            return clamp(Math.round(((Number(value) || 0) / axis) * 100), 0, 100);
        };

        const layerToLegacyEditorItem = function (layer) {
            if (!layer || typeof layer !== 'object') {
                return null;
            }

            const type = String(layer.type || '');
            const role = String(layer.role || '');
            const item = {
                id: String(layer.id || ('layer_' + Date.now())),
                type: '',
                content: '',
                link_url: null,
                x: percentFromPx(layer.x, baseCanvas.width),
                y: percentFromPx(layer.y, baseCanvas.height),
                width: percentFromPx(layer.width, baseCanvas.width),
                height: percentFromPx(layer.height, baseCanvas.height),
                font_size: Math.max(12, Math.round(Number(layer.fontSize) || 18)),
                line_height: Math.max(80, Math.round((Number(layer.lineHeight) || 1.4) * 100)),
                align: ['left', 'center', 'right'].includes(String(layer.textAlign || ''))
                    ? String(layer.textAlign)
                    : 'left',
                bold: Number(layer.fontWeight || 400) >= 700,
                italic: String(layer.fontStyle || 'normal') === 'italic',
                uppercase: Boolean(layer.uppercase),
                shadow: String(layer.shadow || 'off')
            };

            if (type === 'text' && (role === 'title' || role === 'body')) {
                item.type = role;
                item.content = normalizeLayerTextContent(String(layer.textRaw || ''), role !== 'title');
                return item.content !== '' ? item : null;
            }

            if (type === 'button') {
                item.type = 'button';
                item.content = String(layer.textRaw || '');
                item.link_url = String(layer.hrefRaw || '').trim();
                item.align = 'left';
                item.bold = false;
                item.italic = false;
                item.uppercase = false;
                item.shadow = 'off';
                return (item.content !== '' || item.link_url !== '') ? item : null;
            }

            if (type === 'hotspot') {
                item.type = 'hotspot';
                item.content = '';
                item.link_url = String(layer.hrefRaw || '').trim();
                item.font_size = 18;
                item.line_height = 175;
                item.align = 'left';
                item.bold = false;
                item.italic = false;
                item.uppercase = false;
                item.shadow = 'off';
                return item.link_url !== '' ? item : null;
            }

            return null;
        };

        const syncLegacyHiddenState = function (scene) {
            const byRole = sceneLayersByRole(scene || {});
            const titleLayer = byRole.title || null;
            const bodyLayer = byRole.body || null;
            const buttonLayer = byRole.button || null;
            const hotspotLayer = byRole.image_hotspot || byRole.hotspot || null;
            const legacyItems = [];

            (scene && Array.isArray(scene.layers) ? scene.layers : []).forEach(function (layer) {
                const item = layerToLegacyEditorItem(layer);
                if (item) {
                    legacyItems.push(item);
                }
            });

            if (editorLayersInput) {
                editorLayersInput.value = JSON.stringify(legacyItems);
            }

            setInputValue('show_title', titleLayer ? '1' : '0');
            setInputValue('show_body', bodyLayer ? '1' : '0');
            setInputValue('show_button', buttonLayer ? '1' : '0');
            setInputValue('show_image_hotspot', hotspotLayer ? '1' : '0');

            if (titleInput) {
                const nextTitleValue = titleLayer ? normalizeLayerTextContent(String(titleLayer.textRaw || ''), false) : '';
                if (document.activeElement !== titleInput || String(titleInput.value || '') !== nextTitleValue) {
                    titleInput.value = nextTitleValue;
                }
            }
            if (messageInput) {
                messageInput.value = bodyLayer ? normalizeLayerTextContent(String(bodyLayer.textRaw || ''), true) : '';
            }
            if (buttonLabelInput) {
                const nextButtonLabelValue = buttonLayer ? String(buttonLayer.textRaw || '') : '';
                if (document.activeElement !== buttonLabelInput || String(buttonLabelInput.value || '') !== nextButtonLabelValue) {
                    buttonLabelInput.value = nextButtonLabelValue;
                }
            }
            if (linkInput) {
                const nextLinkValue = buttonLayer ? String(buttonLayer.hrefRaw || '').trim() : '';
                if (document.activeElement !== linkInput || String(linkInput.value || '') !== nextLinkValue) {
                    linkInput.value = nextLinkValue;
                }
            }
            if (buttonFontSizeInput) {
                const nextButtonFontSizeValue = buttonLayer ? String(Math.max(12, Math.round(Number(buttonLayer.fontSize) || 18))) : '';
                if (document.activeElement !== buttonFontSizeInput || String(buttonFontSizeInput.value || '') !== nextButtonFontSizeValue) {
                    buttonFontSizeInput.value = nextButtonFontSizeValue;
                }
            }
            if (imageLinkInput) {
                imageLinkInput.value = hotspotLayer ? String(hotspotLayer.hrefRaw || '').trim() : '';
            }

            setInputValue('title_x', titleLayer ? percentFromPx(titleLayer.x, baseCanvas.width) : '');
            setInputValue('title_y', titleLayer ? percentFromPx(titleLayer.y, baseCanvas.height) : '');
            setInputValue('title_width', titleLayer ? percentFromPx(titleLayer.width, baseCanvas.width) : '');
            setInputValue('body_x', bodyLayer ? percentFromPx(bodyLayer.x, baseCanvas.width) : '');
            setInputValue('body_y', bodyLayer ? percentFromPx(bodyLayer.y, baseCanvas.height) : '');
            setInputValue('body_width', bodyLayer ? percentFromPx(bodyLayer.width, baseCanvas.width) : '');
            setInputValue('button_x', buttonLayer ? percentFromPx(buttonLayer.x, baseCanvas.width) : '');
            setInputValue('button_y', buttonLayer ? percentFromPx(buttonLayer.y, baseCanvas.height) : '');
            setInputValue('button_width', buttonLayer ? percentFromPx(buttonLayer.width, baseCanvas.width) : '');
            setInputValue('button_height', buttonLayer ? percentFromPx(buttonLayer.height, baseCanvas.height) : '');
            setInputValue('button_font_size', buttonLayer ? Math.max(12, Math.round(Number(buttonLayer.fontSize) || 18)) : '');
            setInputValue('image_hotspot_x', hotspotLayer ? percentFromPx(hotspotLayer.x, baseCanvas.width) : '');
            setInputValue('image_hotspot_y', hotspotLayer ? percentFromPx(hotspotLayer.y, baseCanvas.height) : '');
            setInputValue('image_hotspot_width', hotspotLayer ? percentFromPx(hotspotLayer.width, baseCanvas.width) : '');
            setInputValue('image_hotspot_height', hotspotLayer ? percentFromPx(hotspotLayer.height, baseCanvas.height) : '');

            setInputValue('title_size', titleLayer ? Math.max(12, Math.round(Number(titleLayer.fontSize) || 18)) : '');
            setInputValue('body_size', bodyLayer ? Math.max(12, Math.round(Number(bodyLayer.fontSize) || 18)) : '');
            setInputValue('title_line_height', titleLayer ? Math.max(80, Math.round((Number(titleLayer.lineHeight) || 1.04) * 100)) : '');
            setInputValue('body_line_height', bodyLayer ? Math.max(100, Math.round((Number(bodyLayer.lineHeight) || 1.4) * 100)) : '');
            setInputValue('title_align', titleLayer ? String(titleLayer.textAlign || 'left') : 'left');
            setInputValue('body_align', bodyLayer ? String(bodyLayer.textAlign || 'left') : 'left');
            setInputValue('title_bold', titleLayer && Number(titleLayer.fontWeight || 400) >= 700 ? '1' : '0');
            setInputValue('body_bold', bodyLayer && Number(bodyLayer.fontWeight || 400) >= 700 ? '1' : '0');
            setInputValue('title_italic', titleLayer && String(titleLayer.fontStyle || 'normal') === 'italic' ? '1' : '0');
            setInputValue('body_italic', bodyLayer && String(bodyLayer.fontStyle || 'normal') === 'italic' ? '1' : '0');
            setInputValue('title_uppercase', titleLayer && titleLayer.uppercase ? '1' : '0');
            setInputValue('body_uppercase', bodyLayer && bodyLayer.uppercase ? '1' : '0');
            setInputValue('title_shadow', titleLayer ? String(titleLayer.shadow || 'off') : 'off');
            setInputValue('body_shadow', bodyLayer ? String(bodyLayer.shadow || 'off') : 'off');
            setInputValue('title_color', titleLayer ? String(titleLayer.color || '#fff7f0') : '#fff7f0');
            setInputValue('body_color', bodyLayer ? String(bodyLayer.color || '#2c1917') : '#2c1917');

            dbg('syncLegacyHiddenState', {
                editorLayersBytes: editorLayersInput ? String(editorLayersInput.value || '').length : 0,
                legacyLayerCount: legacyItems.length,
                hasTitle: Boolean(titleLayer),
                hasBody: Boolean(bodyLayer),
                hasButton: Boolean(buttonLayer),
                hasHotspot: Boolean(hotspotLayer)
            });
        };

        const buildSceneFromCanvas = function () {
            const baseScene = sceneRenderer
                ? sceneRenderer.parseSceneJson(fabricSceneInput.value || config.fabricSceneJson || config.sceneJson || '')
                : {
                    schemaVersion: 1,
                    canvas: { width: baseCanvas.width, height: baseCanvas.height, backgroundImage: '' },
                    layers: [],
                    actions: {}
                };

            const backgroundImage = heroObjectUrl
                || (currentHeroPathInput ? String(currentHeroPathInput.value || '').trim() : '')
                || (baseScene.canvas && baseScene.canvas.backgroundImage ? String(baseScene.canvas.backgroundImage) : '');

            const scene = {
                schemaVersion: 1,
                canvas: {
                    width: currentCanvasSize().width,
                    height: currentCanvasSize().height,
                    backgroundImage: backgroundImage
                },
                actions: {
                    primaryHrefRaw: '',
                    imageHrefRaw: ''
                },
                layers: []
            };

            canvas.getObjects().forEach(function (object) {
                const layer = objectToSceneLayer(object);
                if (layer) {
                    scene.layers.push(layer);
                }
            });

            return sceneRenderer && typeof sceneRenderer.normalizeScene === 'function'
                ? sceneRenderer.normalizeScene(scene)
                : scene;
        };

        const syncHiddenScene = function () {
            if (isLoadingScene) {
                return;
            }

            const scene = buildSceneFromCanvas();
            const json = JSON.stringify(scene);
            fabricSceneInput.value = json;
            sceneInput.value = json;
            syncLegacyHiddenState(scene);
            dbg('syncHiddenScene', {
                sceneBytes: json.length,
                layerCount: scene && Array.isArray(scene.layers) ? scene.layers.length : 0,
                canvasWidth: scene && scene.canvas ? Number(scene.canvas.width || 0) : 0,
                canvasHeight: scene && scene.canvas ? Number(scene.canvas.height || 0) : 0
            });
        };

        const setCanvasBackground = async function (url, options) {
            const opts = options && typeof options === 'object' ? options : {};
            dbg('setCanvasBackground: inicio', {
                urlLen: String(url || '').length,
                adaptWorkspace: opts.adaptWorkspace === true,
                scaleObjects: opts.scaleObjects === true
            });
            canvas.backgroundColor = '#f2e5d8';
            if (!url) {
                canvas.backgroundImage = null;
                canvas.requestRenderAll();
                refreshCanvasShellState();
                dbg('setCanvasBackground: sem imagem', {
                    canvasWidth: currentCanvasSize().width,
                    canvasHeight: currentCanvasSize().height
                });
                return;
            }

            try {
                const crossOrigin = /^https?:\/\//i.test(url) && !isSameOriginAssetUrl(url) ? 'anonymous' : null;
                const image = await fabricLib.Image.fromURL(url, { crossOrigin: crossOrigin });
                const workspace = currentCanvasSize();
                /* contain: mostra a imagem inteira dentro do card (cover cortava bordas e parecia "fora do quadro") */
                const scale = Math.min(
                    workspace.width / image.width,
                    workspace.height / image.height
                );
                const scaledW = image.width * scale;
                const scaledH = image.height * scale;
                image.set({
                    left: (workspace.width - scaledW) / 2,
                    top: (workspace.height - scaledH) / 2,
                    originX: 'left',
                    originY: 'top',
                    selectable: false,
                    evented: false,
                    scaleX: scale,
                    scaleY: scale
                });
                canvas.backgroundImage = image;
                canvas.requestRenderAll();
                dbg('setCanvasBackground: sucesso', {
                    imageWidth: image.width,
                    imageHeight: image.height,
                    workspaceWidth: workspace.width,
                    workspaceHeight: workspace.height,
                    scale: scale
                });
            } catch (error) {
                canvas.backgroundImage = null;
                canvas.requestRenderAll();
                dbg('setCanvasBackground: erro', {
                    message: String(error && error.message ? error.message : error),
                    url: String(url || '')
                });
            }
            refreshCanvasShellState();
        };

        const resolveSceneJsonRaw = function (rawScene) {
            const explicit = String(rawScene == null ? '' : rawScene).trim();
            if (explicit !== '') {
                return explicit;
            }
            const fromBoot = readBootSceneText();
            if (fromBoot !== '') {
                return fromBoot;
            }
            const fromConfig = String(config.fabricSceneJson || config.sceneJson || '').trim();
            if (fromConfig !== '') {
                return fromConfig;
            }
            return String(fabricSceneInput.value || sceneInput.value || '').trim();
        };

        const addSceneLayersToCanvas = function (scene) {
            let added = 0;
            if (!scene || !Array.isArray(scene.layers)) {
                return added;
            }
            sceneToObjects(scene).forEach(function (object) {
                if (!object) {
                    return;
                }
                try {
                    canvas.add(object);
                    added += 1;
                } catch (err) {
                    const meta = layerMetaByObject.get(object) || object.data || {};
                    dbg('addSceneLayersToCanvas: add_error', {
                        id: String(meta.layerId || ''),
                        type: String(meta.layerType || ''),
                        message: String(err && err.message ? err.message : err)
                    });
                }
            });
            return added;
        };

        const loadSceneIntoCanvas = async function (rawScene) {
            const jsonRaw = resolveSceneJsonRaw(rawScene);
            const hasBootSceneSource = jsonRaw !== '';
            let scene = sceneRenderer ? sceneRenderer.parseSceneJson(jsonRaw) : null;
            const legacyScene = buildSceneFromLegacyEditorLayersJson();

            dbg('loadSceneIntoCanvas: JSON resolvido', {
                rawSceneExplicitLen: String(rawScene == null ? '' : rawScene).trim().length,
                jsonRawLen: jsonRaw.length,
                parsedLayerCount: scene && Array.isArray(scene.layers) ? scene.layers.length : 0,
                usedMessageSceneRenderer: Boolean(sceneRenderer)
            });

            if (
                scene
                && Array.isArray(scene.layers)
                && scene.layers.length > 0
                && legacyScene
                && shouldPreferLegacyScene(scene, legacyScene)
            ) {
                dbg('loadSceneIntoCanvas: preferindo legado normalizado', {
                    parsedLayerCount: scene.layers.length,
                    legacyLayerCount: legacyScene.layers ? legacyScene.layers.length : 0
                });
                scene = legacyScene;
            }

            if (
                (!scene || !Array.isArray(scene.layers) || !sceneHasRenderableContent(scene))
                && legacyScene
                && sceneHasRenderableContent(legacyScene)
            ) {
                scene = legacyScene;
                dbg('loadSceneIntoCanvas: fallback editor_layers_json', {
                    layerCount: legacyScene.layers ? legacyScene.layers.length : 0
                });
            }

            if (!scene || !Array.isArray(scene.layers)) {
                scene = emptyScene();
            }

            if (!sceneHasRenderableContent(scene) && bootProjectId === '') {
                clearEditorFormStateForEmptyBoot();
                scene = emptyScene();
                dbg('loadSceneIntoCanvas: boot vazio explicito', {
                    bootProjectId: bootProjectId,
                    sceneLayerCount: Array.isArray(scene.layers) ? scene.layers.length : 0,
                    hasBootSceneSource: hasBootSceneSource
                });
            }

            scene = normalizeSceneToBaseCanvas(scene);
            scene = migrateSceneHotspotsToPhotoArea(scene);
            const normalizedLegacyScene = legacyScene
                ? migrateSceneHotspotsToPhotoArea(normalizeSceneToBaseCanvas(legacyScene))
                : null;

            const bgUrl = heroObjectUrl || currentHeroUrl() || (scene && scene.canvas && scene.canvas.backgroundImage
                ? String(scene.canvas.backgroundImage)
                : '');
            const sceneSize = sceneCanvasSize(scene);
            const shouldAutoFitBackground = false;
            const shouldAutoFitContent = false;

            dbg('loadSceneIntoCanvas: preparando canvas', {
                backgroundUrlLen: String(bgUrl || '').length,
                sceneWidth: sceneSize.width,
                sceneHeight: sceneSize.height,
                shouldAutoFitBackground: shouldAutoFitBackground,
                shouldAutoFitContent: shouldAutoFitContent
            });

            isLoadingScene = true;
            canvas.clear();
            canvas.backgroundColor = '#f2e5d8';
            canvas.backgroundImage = null;
            setWorkspaceDimensions(sceneSize.width, sceneSize.height);
            refreshCanvasShellState();

            let added = addSceneLayersToCanvas(scene);
            if (added === 0) {
                if (
                    sceneHasRenderableContent(scene)
                    && normalizedLegacyScene
                    && Array.isArray(normalizedLegacyScene.layers)
                    && normalizedLegacyScene.layers.length > 0
                ) {
                    added = addSceneLayersToCanvas(normalizedLegacyScene);
                }
            }

            const objectsOnCanvas = canvas.getObjects().length;
            refreshCanvasShellState();
            dbg('loadSceneIntoCanvas: objetos no canvas (apos add)', {
                objetosAdicionadosNaUltimaTentativaComContagem: added,
                canvasGetObjectsCount: objectsOnCanvas,
                backgroundUrlLen: String(bgUrl || '').length
            });

            if (typeof canvas.renderAll === 'function') {
                canvas.renderAll();
            }
            canvas.requestRenderAll();
            isLoadingScene = false;
            syncHiddenScene();

            try {
                await Promise.race([
                    setCanvasBackground(bgUrl, {
                        adaptWorkspace: shouldAutoFitBackground || shouldAutoFitContent,
                        scaleObjects: shouldAutoFitBackground
                    }),
                    new Promise(function (resolve) {
                        window.setTimeout(resolve, 12000);
                    })
                ]);
            } catch (err) {
                await setCanvasBackground('', { adaptWorkspace: shouldAutoFitContent });
            }
            canvas.discardActiveObject();
            syncHiddenScene();

            dbg('loadSceneIntoCanvas: fim (apos fundo)', {
                canvasGetObjectsCount: canvas.getObjects().length,
                fabricSceneHiddenLen: String(fabricSceneInput.value || '').length
            });

            if (typeof canvas.calcOffset === 'function') {
                canvas.calcOffset();
            }
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    if (typeof canvas.renderAll === 'function') {
                        canvas.renderAll();
                    }
                    canvas.requestRenderAll();
                });
            });
        };

        const addLayer = function (kind) {
            let layer = null;
            if (kind === 'title') {
                layer = defaultTextLayer('title');
            } else if (kind === 'body') {
                layer = defaultTextLayer('body');
            } else if (kind === 'button') {
                layer = defaultButtonLayer();
            } else if (kind === 'hotspot') {
                layer = defaultHotspotLayer();
                layer.hrefRaw = String(imageLinkInput && imageLinkInput.value ? imageLinkInput.value : '').trim();
            }

            if (!layer) {
                dbg('tracked:add_layer_failed', {
                    requestedKind: String(kind || ''),
                    reason: 'layer_factory_returned_null'
                });
                return;
            }

            const object = sceneToObjects({ layers: [layer] })[0];
            if (!object) {
                dbg('tracked:add_layer_failed', {
                    requestedKind: String(kind || ''),
                    reason: 'scene_to_objects_returned_null',
                    layerId: String(layer.id || ''),
                    layerType: String(layer.type || '')
                });
                return;
            }

            canvas.add(object);
            canvas.setActiveObject(object);
            syncWorkspaceToObjects({ allowShrink: false });
            canvas.requestRenderAll();
            syncHiddenScene();

            const meta = layerMetaByObject.get(object) || object.data || {};
            dbg('tracked:add_layer_completed', {
                requestedKind: String(kind || ''),
                layerId: String(meta.layerId || layer.id || ''),
                layerType: String(meta.layerType || layer.type || ''),
                layerRole: String(meta.layerRole || layer.role || ''),
                canvasObjectCount: canvas.getObjects().length,
                canvasWidth: currentCanvasSize().width,
                canvasHeight: currentCanvasSize().height,
                fabricSceneBytes: String(fabricSceneInput.value || '').length,
                sceneBytes: String(sceneInput.value || '').length,
                editorLayersBytes: editorLayersInput ? String(editorLayersInput.value || '').length : 0,
                topLeft: objectTopLeft(object),
                scaledSize: objectScaledSize(object)
            });

            if (kind === 'title' || kind === 'body') {
                window.requestAnimationFrame(function () {
                    beginTextEditing(object, { selectAll: true });
                });
            } else {
                refreshInspector();
            }
        };

        const selectedObject = function () {
            return canvas.getActiveObject() || null;
        };

        const selectedMeta = function () {
            const active = selectedObject();
            if (!active || active.type === 'activeSelection') {
                return null;
            }

            return layerMetaByObject.get(active) || active.data || null;
        };

        const findCanvasObjectByLayer = function (layerType, layerRole) {
            let found = null;

            canvas.getObjects().some(function (object) {
                const meta = layerMetaByObject.get(object) || object.data || null;
                if (!meta) {
                    return false;
                }

                if (String(meta.layerType || '') !== String(layerType || '')) {
                    return false;
                }

                if (String(meta.layerRole || '') !== String(layerRole || '')) {
                    return false;
                }

                found = object;
                return true;
            });

            return found;
        };

        const syncTextInputToCanvasLayer = function (role, value, options) {
            const opts = options && typeof options === 'object' ? options : {};
            const preserveParagraphs = String(role || '') !== 'title';
            const normalized = normalizeLayerTextContent(value, preserveParagraphs);
            let object = findCanvasObjectByLayer('text', role);

            if (!object) {
                if (normalized === '' || opts.createIfMissing === false) {
                    return false;
                }

                const nextLayer = defaultTextLayer(role);
                nextLayer.textRaw = normalized;
                object = sceneToObjects({ layers: [nextLayer] })[0] || null;
                if (!object) {
                    return false;
                }
                canvas.add(object);
            }

            const meta = layerMetaByObject.get(object) || object.data || null;
            if (!meta) {
                return false;
            }

            meta.textRaw = normalized;
            if (object.data && typeof object.data === 'object') {
                object.data.textRaw = normalized;
            }

            syncTextObjectFromMeta(object, meta);

            if (opts.activate === true) {
                canvas.setActiveObject(object);
            }

            if (typeof object.setCoords === 'function') {
                object.setCoords();
            }

            canvas.requestRenderAll();
            syncHiddenScene();
            refreshInspector();

            return true;
        };

        const syncButtonInputsToCanvasLayer = function (options) {
            const opts = options && typeof options === 'object' ? options : {};
            if (isPointerTransformActive && opts.forceSync !== true) {
                return false;
            }
            const requestedLabel = String(buttonLabelInput && typeof buttonLabelInput.value !== 'undefined' ? buttonLabelInput.value : '');
            const requestedHref = String(linkInput && linkInput.value ? linkInput.value : '').trim();
            const rawRequestedFontSize = Math.round(Number(buttonFontSizeInput && buttonFontSizeInput.value ? buttonFontSizeInput.value : 0) || 0);
            const requestedFontSize = rawRequestedFontSize > 0
                ? clamp(rawRequestedFontSize, 12, 96)
                : 0;
            let object = findCanvasObjectByLayer('button', 'button');

            if (!object) {
                if (requestedLabel.trim() === '' && requestedHref === '' && opts.createIfMissing !== true) {
                    return false;
                }

                const nextLayer = defaultButtonLayer();
                nextLayer.textRaw = requestedLabel;
                nextLayer.hrefRaw = requestedHref;
                nextLayer.fontSize = requestedFontSize || nextLayer.fontSize;
                object = sceneToObjects({ layers: [nextLayer] })[0] || null;
                if (!object) {
                    return false;
                }
                canvas.add(object);
            }

            const meta = layerMetaByObject.get(object) || object.data || null;
            if (!meta) {
                return false;
            }

            meta.textRaw = requestedLabel;
            meta.hrefRaw = requestedHref;
            meta.fontSize = requestedFontSize || Math.max(12, Math.round(Number(meta.fontSize) || 18));

            if (object.data && typeof object.data === 'object') {
                object.data.textRaw = meta.textRaw;
                object.data.hrefRaw = meta.hrefRaw;
                object.data.fontSize = meta.fontSize;
            }

            syncButtonGroupText(object);

            if (opts.activate === true) {
                canvas.setActiveObject(object);
            }

            if (typeof object.setCoords === 'function') {
                object.setCoords();
            }

            canvas.requestRenderAll();
            syncHiddenScene();
            refreshInspector();

            return true;
        };

        const debugTextEditState = function (step, payload) {
            const active = selectedObject();
            const activeMeta = active ? (layerMetaByObject.get(active) || active.data || null) : null;
            const overlayMeta = overlayEditingObject
                ? (layerMetaByObject.get(overlayEditingObject) || overlayEditingObject.data || null)
                : null;
            const extra = payload && typeof payload === 'object' ? payload : {};

            dbg(step, Object.assign({
                activeLayerId: String(activeMeta && activeMeta.layerId || ''),
                activeLayerType: String(activeMeta && activeMeta.layerType || ''),
                activeRole: String(activeMeta && activeMeta.layerRole || ''),
                activeObjectTextBytes: active ? String(fabricObjectText(active) || '').length : 0,
                activeMetaTextBytes: activeMeta ? String(activeMeta.textRaw || '').length : 0,
                overlayEditingLayerId: String(overlayMeta && overlayMeta.layerId || ''),
                overlayEditingRole: String(overlayMeta && overlayMeta.layerRole || ''),
                overlayValueBytes: overlayEditor ? String(overlayEditor.value || '').length : 0,
                overlayHidden: overlayEditor ? overlayEditor.hidden !== false : true,
                hasOverlayEditor: Boolean(overlayEditor)
            }, extra));
        };

        const syncTextObjectFromMeta = function (object, meta, options) {
            const opts = options && typeof options === 'object' ? options : {};
            if (!object || !meta || String(meta.layerType || '') !== 'text') {
                return;
            }

            const preserveParagraphs = String(meta.layerRole || 'body') !== 'title';
            const rawValue = String(meta.textRaw || '');
            meta.textRaw = opts.normalize === false
                ? rawValue.replace(/\r\n?/g, '\n')
                : normalizeLayerTextContent(rawValue, preserveParagraphs);

            object.set({
                text: visibleTextForEditor({
                    role: meta.layerRole,
                    textRaw: meta.textRaw
                }),
                shadow: objectShadow({ shadow: meta.shadow })
            });

            if (typeof object.setCoords === 'function') {
                object.setCoords();
            }
        };

        const setOverlayEditingVisibility = function (object, isEditing) {
            if (!object) {
                return;
            }

            if (isEditing) {
                overlayEditingOriginalOpacity = Number.isFinite(Number(object.opacity))
                    ? Number(object.opacity)
                    : 1;
                object.set('opacity', 0);
            } else {
                const restoredOpacity = Number.isFinite(Number(overlayEditingOriginalOpacity))
                    ? Number(overlayEditingOriginalOpacity)
                    : 1;
                object.set('opacity', restoredOpacity);
                overlayEditingOriginalOpacity = null;
            }

            if (typeof object.setCoords === 'function') {
                object.setCoords();
            }
        };

        const refreshInspector = function () {
            if (!overlayEditor || !overlayEditingObject) {
                return;
            }

            const meta = layerMetaByObject.get(overlayEditingObject) || overlayEditingObject.data || null;
            if (!meta || String(meta.layerType || '') !== 'text') {
                return;
            }

            const topLeft = objectTopLeft(overlayEditingObject);
            const size = objectScaledSize(overlayEditingObject);
            const minHeight = Math.max(42, size.height || Math.round((overlayEditingObject.fontSize || 18) * (overlayEditingObject.lineHeight || 1.4)));

            overlayEditor.style.left = topLeft.x + 'px';
            overlayEditor.style.top = topLeft.y + 'px';
            overlayEditor.style.width = Math.max(80, size.width) + 'px';
            overlayEditor.style.minHeight = minHeight + 'px';
            overlayEditor.style.fontFamily = String(overlayEditingObject.fontFamily || 'Arial, Helvetica, sans-serif');
            overlayEditor.style.fontSize = Math.max(12, Number(overlayEditingObject.fontSize || 18)) + 'px';
            overlayEditor.style.fontWeight = Number(overlayEditingObject.fontWeight || 400) >= 700 ? '800' : '400';
            overlayEditor.style.fontStyle = String(overlayEditingObject.fontStyle || 'normal') === 'italic' ? 'italic' : 'normal';
            overlayEditor.style.lineHeight = String(clamp(overlayEditingObject.lineHeight || 1.4, 0.7, 4));
            overlayEditor.style.textAlign = ['left', 'center', 'right'].includes(String(overlayEditingObject.textAlign || 'left'))
                ? String(overlayEditingObject.textAlign)
                : 'left';
            overlayEditor.style.color = String(overlayEditingObject.fill || '#ffffff');
            overlayEditor.style.textShadow = (function () {
                if (String(meta.shadow || 'off') === 'strong') {
                    return '0 4px 14px rgba(0, 0, 0, 0.32)';
                }
                if (String(meta.shadow || 'off') === 'soft') {
                    return '0 2px 8px rgba(0, 0, 0, 0.18)';
                }
                return 'none';
            }());

            overlayEditor.style.height = 'auto';
            overlayEditor.style.height = Math.max(minHeight, overlayEditor.scrollHeight) + 'px';
        };

        const renderCanvasState = function () {
            canvas.requestRenderAll();
            refreshInspector();
        };

        const forceCanvasRepaint = function () {
            if (typeof canvas.clearContext === 'function' && canvas.contextTop) {
                canvas.clearContext(canvas.contextTop);
            }
            if (typeof canvas.renderAll === 'function') {
                canvas.renderAll();
            }
            canvas.requestRenderAll();
            refreshInspector();
        };

        const commitCanvasState = function () {
            renderCanvasState();
            syncHiddenScene();
        };

        const ensureOverlayEditor = function () {
            if (overlayEditor) {
                return overlayEditor;
            }

            if (!canvas || !canvas.wrapperEl) {
                return null;
            }

            overlayEditor = document.createElement('textarea');
            overlayEditor.className = 'message-fabric-editor__text-editor';
            overlayEditor.hidden = true;
            overlayEditor.setAttribute('aria-label', 'Editor de texto da camada selecionada');
            overlayEditor.setAttribute('spellcheck', 'false');
            overlayEditor.setAttribute('autocomplete', 'off');
            overlayEditor.setAttribute('autocapitalize', 'off');
            overlayEditor.setAttribute('autocorrect', 'off');

            overlayEditor.addEventListener('input', function () {
                const meta = overlayEditingObject ? (layerMetaByObject.get(overlayEditingObject) || overlayEditingObject.data || null) : null;
                if (!overlayEditingObject || !meta) {
                    return;
                }

                meta.textRaw = String(overlayEditor.value || '').replace(/\r\n?/g, '\n');
                syncTextObjectFromMeta(overlayEditingObject, meta, { normalize: false });
                debugTextEditState('overlay_editor: input', {
                    inputValueBytes: String(overlayEditor.value || '').length
                });
                renderCanvasState();
            });

            overlayEditor.addEventListener('blur', function () {
                debugTextEditState('overlay_editor: blur');
                window.setTimeout(function () {
                    if (overlayEditor && document.activeElement !== overlayEditor && overlayEditingObject) {
                        exitTextEditing({ keepCanvasFocus: true });
                    }
                }, 0);
            });

            overlayEditor.addEventListener('keydown', function (event) {
                debugTextEditState('overlay_editor: keydown', {
                    key: String(event.key || '')
                });
                if (event.key === 'Escape') {
                    event.preventDefault();
                    exitTextEditing({ keepCanvasFocus: false });
                }
            });

            canvas.wrapperEl.appendChild(overlayEditor);
            return overlayEditor;
        };

        const beginTextEditing = function (target, options) {
            const opts = options && typeof options === 'object' ? options : {};
            const object = target || selectedObject();
            const meta = object ? (layerMetaByObject.get(object) || object.data || null) : null;
            debugTextEditState('beginTextEditing: start', {
                targetLayerId: String(meta && meta.layerId || ''),
                targetRole: String(meta && meta.layerRole || ''),
                targetType: String(meta && meta.layerType || ''),
                targetObjectTextBytes: object ? String(fabricObjectText(object) || '').length : 0,
                targetMetaTextBytes: meta ? String(meta.textRaw || '').length : 0,
                selectAll: opts.selectAll === true
            });
            if (!object || !meta || String(meta.layerType || '') !== 'text') {
                debugTextEditState('beginTextEditing: aborted', {
                    reason: 'target_not_text'
                });
                return false;
            }

            const editor = ensureOverlayEditor();
            if (!editor) {
                debugTextEditState('beginTextEditing: aborted', {
                    reason: 'overlay_editor_unavailable'
                });
                return false;
            }

            if (overlayEditingObject && overlayEditingObject !== object) {
                debugTextEditState('beginTextEditing: switching_target', {
                    previousLayerId: String((overlayEditingObject.data && overlayEditingObject.data.layerId) || '')
                });
                exitTextEditing({ keepCanvasFocus: true });
            }

            canvas.setActiveObject(object);
            overlayEditingObject = object;
            setOverlayEditingVisibility(object, true);
            editor.hidden = false;
            editor.value = String(meta.textRaw || fabricObjectText(object) || '').replace(/\r\n?/g, '\n');
            debugTextEditState('beginTextEditing: armed', {
                editorValueBytes: String(editor.value || '').length
            });
            refreshInspector();
            window.requestAnimationFrame(function () {
                editor.focus();
                if (opts.selectAll) {
                    editor.select();
                    debugTextEditState('beginTextEditing: focus_ready', {
                        selectionMode: 'all'
                    });
                    return;
                }
                const length = editor.value.length;
                if (typeof editor.setSelectionRange === 'function') {
                    editor.setSelectionRange(length, length);
                }
                debugTextEditState('beginTextEditing: focus_ready', {
                    selectionMode: 'end',
                    caretAt: length
                });
            });
            renderCanvasState();
            return true;
        };

        const exitTextEditing = function (options) {
            const opts = options && typeof options === 'object' ? options : {};
            debugTextEditState('exitTextEditing: start', {
                keepCanvasFocus: opts.keepCanvasFocus === true
            });
            if (!overlayEditor || !overlayEditingObject) {
                debugTextEditState('exitTextEditing: aborted', {
                    reason: 'overlay_not_active'
                });
                return false;
            }

            const object = overlayEditingObject;
            const meta = layerMetaByObject.get(object) || object.data || null;
            if (meta) {
                meta.textRaw = String(overlayEditor.value || '');
                syncTextObjectFromMeta(object, meta, { normalize: true });
            }

            overlayEditingObject = null;
            setOverlayEditingVisibility(object, false);
            overlayEditor.hidden = true;
            overlayEditor.value = '';

            if (!opts.keepCanvasFocus && canvas.upperCanvasEl && typeof canvas.upperCanvasEl.focus === 'function') {
                canvas.upperCanvasEl.focus();
            }

            debugTextEditState('exitTextEditing: committed', {
                committedValueBytes: meta ? String(meta.textRaw || '').length : 0
            });
            commitCanvasState();
            refreshInspector();
            return true;
        };

        const nudgeSelected = function (deltaX, deltaY) {
            const active = selectedObject();
            const meta = selectedMeta();
            if (!active || !meta || overlayEditingObject) {
                return false;
            }

            active.set({
                left: Math.round(clamp((Number(active.left) || 0) + deltaX, 0, baseCanvas.width)),
                top: Math.round(clamp((Number(active.top) || 0) + deltaY, 0, baseCanvas.height))
            });
            if (typeof active.setCoords === 'function') {
                active.setCoords();
            }
            commitCanvasState();
            return true;
        };

        const duplicateSelected = function () {
            const active = selectedObject();
            if (!active) {
                return;
            }

            const layer = objectToSceneLayer(active);
            if (!layer) {
                return;
            }

            layer.id = String(layer.id || 'layer') + '_copy_' + Date.now();
            layer.x = Math.round((layer.x || 0) + 24);
            layer.y = Math.round((layer.y || 0) + 24);

            const object = sceneToObjects({ layers: [layer] })[0];
            if (!object) {
                return;
            }

            canvas.add(object);
            canvas.setActiveObject(object);
            syncWorkspaceToObjects({ allowShrink: false });
            canvas.requestRenderAll();
            syncHiddenScene();
            refreshInspector();
        };

        const deleteSelected = function () {
            const active = selectedObject();
            if (!active) {
                return;
            }

            if (active.type === 'activeSelection') {
                active.forEachObject(function (object) {
                    canvas.remove(object);
                });
            } else {
                canvas.remove(active);
            }

            canvas.discardActiveObject();
            syncWorkspaceToObjects({ allowShrink: true });
            canvas.requestRenderAll();
            syncHiddenScene();
            refreshInspector();
        };

        root.querySelectorAll('[data-fabric-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                addLayer(String(button.dataset.fabricAdd || ''));
            });
        });

        document.querySelectorAll('[data-add-layer]').forEach(function (button) {
            button.addEventListener('click', function () {
                addLayer(String(button.dataset.addLayer || ''));
            });
        });

        root.querySelectorAll('[data-fabric-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                const action = String(button.dataset.fabricAction || '');
                if (action === 'edit-text') {
                    debugTextEditState('toolbar: edit_text_click');
                    beginTextEditing(selectedObject(), { selectAll: false });
                    return;
                }
                if (action === 'duplicate') {
                    duplicateSelected();
                    return;
                }
                if (action === 'delete') {
                    deleteSelected();
                    return;
                }
            });
        });

        canvas.on('mouse:dblclick', function (event) {
            const target = event && event.target ? event.target : selectedObject();
            const meta = target ? (layerMetaByObject.get(target) || target.data || null) : null;
            debugTextEditState('canvas: mouse_dblclick', {
                handler: 'fabric_canvas',
                targetLayerId: String(meta && meta.layerId || ''),
                targetType: String(meta && meta.layerType || ''),
                targetRole: String(meta && meta.layerRole || '')
            });
            beginTextEditing(target, { selectAll: false });
        });

        canvas.on('mouse:down', function (event) {
            isPointerTransformActive = Boolean(event && event.target);
        });

        canvas.on('object:moving', function (event) {
            isPointerTransformActive = true;
            if (event && event.target && typeof event.target.setCoords === 'function') {
                event.target.setCoords();
            }
            canvas.requestRenderAll();
            refreshInspector();
        });

        canvas.on('object:scaling', function (event) {
            isPointerTransformActive = true;
            if (event && event.target && typeof event.target.setCoords === 'function') {
                event.target.setCoords();
            }
            canvas.requestRenderAll();
            refreshInspector();
        });

        canvas.on('mouse:up', function () {
            isPointerTransformActive = false;
            forceCanvasRepaint();
        });

        canvas.on('selection:created', refreshInspector);
        canvas.on('selection:updated', refreshInspector);
        canvas.on('selection:cleared', refreshInspector);

        canvas.on('object:modified', function (event) {
            if (event && event.target) {
                normalizeScaledObject(event.target);
                const meta = layerMetaByObject.get(event.target) || event.target.data || {};
                dbg('canvas: object_modified', {
                    id: String(meta.layerId || ''),
                    type: String(meta.layerType || ''),
                    left: Number(event.target.left || 0),
                    top: Number(event.target.top || 0),
                    width: Number(event.target.width || 0),
                    height: Number(event.target.height || 0),
                    scaleX: Number(event.target.scaleX || 1),
                    scaleY: Number(event.target.scaleY || 1)
                });
            }
            syncWorkspaceToObjects({ allowShrink: false });
            syncHiddenScene();
            refreshInspector();
        });
        canvas.on('object:added', function (event) {
            const target = event && event.target;
            const meta = target ? (layerMetaByObject.get(target) || target.data || {}) : {};
            dbg('canvas: object_added', {
                id: String(meta.layerId || ''),
                type: String(meta.layerType || ''),
                objectsOnCanvas: canvas.getObjects().length
            });
            syncHiddenScene();
            refreshInspector();
        });
        canvas.on('object:removed', function (event) {
            const target = event && event.target;
            const meta = target ? (layerMetaByObject.get(target) || target.data || {}) : {};
            dbg('canvas: object_removed', {
                id: String(meta.layerId || ''),
                type: String(meta.layerType || ''),
                objectsOnCanvas: canvas.getObjects().length
            });
            syncHiddenScene();
            refreshInspector();
        });
        canvas.on('text:changed', function (event) {
            const t = event && event.target;
            if (t) {
                const m = layerMetaByObject.get(t) || t.data;
                if (m) {
                    const sanitized = normalizeLayerTextContent(
                        String(fabricObjectText(t) || ''),
                        String(m.layerRole || 'body') !== 'title'
                    );
                    if (sanitized !== String(fabricObjectText(t) || '')) {
                        t.set('text', sanitized);
                        if (typeof t.setCoords === 'function') {
                            t.setCoords();
                        }
                    }
                    m.textRaw = sanitized;
                    dbg('canvas: text_changed', {
                        id: String(m.layerId || ''),
                        type: String(m.layerType || ''),
                        textBytes: String(m.textRaw || '').length
                    });
                }
            }
            syncHiddenScene();
            refreshInspector();
        });

        document.addEventListener('mouseup', function () {
            if (!isPointerTransformActive) {
                return;
            }
            isPointerTransformActive = false;
            forceCanvasRepaint();
        });

        if (imageLinkInput) {
            imageLinkInput.addEventListener('input', function () {
                const value = String(imageLinkInput.value || '').trim();
                let updated = false;

                canvas.getObjects().forEach(function (object) {
                    const meta = layerMetaByObject.get(object) || object.data || null;
                    if (!meta || String(meta.layerType || '') !== 'hotspot') {
                        return;
                    }
                    if (String(meta.layerRole || 'image_hotspot') !== 'image_hotspot') {
                        return;
                    }

                    meta.hrefRaw = value;
                    if (object.data && typeof object.data === 'object') {
                        object.data.hrefRaw = value;
                    }
                    updated = true;
                });

                if (updated) {
                    syncHiddenScene();
                }
            });
        }

        if (buttonLabelInput) {
            buttonLabelInput.addEventListener('input', function () {
                syncButtonInputsToCanvasLayer({
                    createIfMissing: true,
                    activate: false
                });
            });
        }

        if (linkInput) {
            linkInput.addEventListener('input', function () {
                syncButtonInputsToCanvasLayer({
                    createIfMissing: true,
                    activate: false
                });
            });
        }

        if (buttonFontSizeInput) {
            buttonFontSizeInput.addEventListener('input', function () {
                syncButtonInputsToCanvasLayer({
                    createIfMissing: false,
                    activate: false
                });
            });
        }

        if (titleInput) {
            titleInput.addEventListener('input', function () {
                syncTextInputToCanvasLayer('title', String(titleInput.value || ''), {
                    createIfMissing: true,
                    activate: false
                });
            });
        }

        document.addEventListener('keydown', function (event) {
            const active = selectedObject();
            if (!active) {
                return;
            }

            const target = event.target;
            const tagName = target && target.tagName ? String(target.tagName).toLowerCase() : '';
            const isFormField = Boolean(
                target
                && (
                    target.isContentEditable
                    || tagName === 'input'
                    || tagName === 'textarea'
                    || tagName === 'select'
                )
            );

            if (overlayEditingObject) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    exitTextEditing();
                }
                return;
            }

            if (isFormField) {
                return;
            }

            if ((event.ctrlKey || event.metaKey) && String(event.key || '').toLowerCase() === 'd') {
                event.preventDefault();
                duplicateSelected();
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                deleteSelected();
                return;
            }

            if (event.key === 'Enter' && !event.shiftKey) {
                if (beginTextEditing(active, { selectAll: false })) {
                    event.preventDefault();
                }
                return;
            }

            const step = event.shiftKey ? 10 : 1;
            if (event.key === 'ArrowLeft') {
                if (nudgeSelected(-step, 0)) {
                    event.preventDefault();
                }
                return;
            }
            if (event.key === 'ArrowRight') {
                if (nudgeSelected(step, 0)) {
                    event.preventDefault();
                }
                return;
            }
            if (event.key === 'ArrowUp') {
                if (nudgeSelected(0, -step)) {
                    event.preventDefault();
                }
                return;
            }
            if (event.key === 'ArrowDown') {
                if (nudgeSelected(0, step)) {
                    event.preventDefault();
                }
            }
        });

        if (form) {
            form.addEventListener('submit', function () {
                if (titleInput) {
                    syncTextInputToCanvasLayer('title', String(titleInput.value || ''), {
                        createIfMissing: true,
                        activate: false
                    });
                }
                dbg('form: before_submit_sync', {
                    sceneBytesBefore: String(sceneInput.value || '').length,
                    fabricSceneBytesBefore: String(fabricSceneInput.value || '').length,
                    editorLayersBytesBefore: editorLayersInput ? String(editorLayersInput.value || '').length : 0
                });
                syncHiddenScene();
            }, true);
        }

        if (heroInput) {
            heroInput.addEventListener('change', function () {
                if (heroObjectUrl) {
                    URL.revokeObjectURL(heroObjectUrl);
                    heroObjectUrl = '';
                }

                const file = heroInput.files && heroInput.files[0] ? heroInput.files[0] : null;
                if (file) {
                    heroObjectUrl = URL.createObjectURL(file);
                    syncHeroUi(file.name);
                    setCanvasBackground(heroObjectUrl, {
                        adaptWorkspace: true,
                        scaleObjects: true
                    }).then(syncHiddenScene);
                }
            });
        }

        if (heroClearButton) {
            heroClearButton.addEventListener('click', function () {
                if (heroObjectUrl) {
                    URL.revokeObjectURL(heroObjectUrl);
                    heroObjectUrl = '';
                }
                if (heroInput) {
                    heroInput.value = '';
                }
                if (currentHeroPathInput) {
                    currentHeroPathInput.value = '';
                }
                syncHeroUi('');
                setCanvasBackground('', { adaptWorkspace: true }).then(syncHiddenScene);
            });
        }

        setModeLabel();
        syncRecipientVisibility();
        syncHeroUi(heroInput && heroInput.files && heroInput.files[0] ? heroInput.files[0].name : (currentHeroPathInput ? String(currentHeroPathInput.value || '').split(/[\\\\/]/).pop() : ''));
        refreshCanvasShellState();
        refreshInspector();
        if (recipientModeInput) {
            recipientModeInput.addEventListener('change', syncRecipientVisibility);
        }
        loadSceneIntoCanvas('').then(function () {
            refreshInspector();
            var detail = {
                canvasGetObjectsCount: canvas.getObjects().length,
                editorEngine: editorEngineInput.value
            };
            if (typeof window.messageEditorDebugPush === 'function') {
                window.messageEditorDebugPush('Editor V2: init concluido', detail);
            } else {
                dbg('Editor V2: init concluido', detail);
            }
        });
    });
})(window, document);
