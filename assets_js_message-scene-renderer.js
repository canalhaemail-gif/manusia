(function (window) {
    'use strict';

    const canvasDefaults = Object.freeze({
        width: 1080,
        height: 1620
    });
    const emailButtonTextureUrl = 'https://files.manuscdn.com/user_upload_by_module/session_file/310519663565489241/ksArwtVtypUlKkEr.webp';
    let buttonTexturePromise = null;

    const previewTokenValues = Object.freeze({
        '{{primeiro_nome}}': 'Lucas',
        '{{nome}}': 'Lucas Nogueira',
        '{{saudacao_sumido}}': 'Oi, sumido!',
        '{{bem_vindo_ou_vinda}}': 'Seja muito bem-vindo',
        '{{loja}}': 'Moda Tropical'
    });

    const defaultScene = Object.freeze({
        schemaVersion: 1,
        canvas: {
            width: canvasDefaults.width,
            height: canvasDefaults.height,
            backgroundImage: ''
        },
        layers: [],
        actions: {}
    });

    const clamp = function (value, min, max) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return min;
        }

        return Math.min(Math.max(numeric, min), max);
    };

    const resolveTokens = function (value, replacements) {
        let resolved = String(value == null ? '' : value);
        const tokenMap = replacements && typeof replacements === 'object'
            ? replacements
            : previewTokenValues;

        Object.keys(tokenMap).forEach(function (token) {
            resolved = resolved.split(token).join(String(tokenMap[token]));
        });

        return resolved;
    };

    const escapeHtml = function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const normalizeTextAlign = function (value) {
        return ['left', 'center', 'right'].includes(value) ? value : 'left';
    };

    const normalizeTextContent = function (value, preserveParagraphs) {
        const keepParagraphs = preserveParagraphs !== false;
        let normalized = String(value == null ? '' : value)
            .replace(/\r\n?/g, '\n')
            .replace(/\u00a0/g, ' ');

        normalized = normalized.replace(/[ \t]+/g, ' ');
        normalized = normalized
            .split('\n')
            .map(function (line) {
                return line.trim();
            })
            .join('\n');

        if (keepParagraphs) {
            normalized = normalized.replace(/\n{3,}/g, '\n\n');
        } else {
            normalized = normalized.replace(/\s+/g, ' ');
        }

        return normalized.trim();
    };

    const normalizeLayer = function (layer) {
        const source = layer && typeof layer === 'object' ? layer : {};
        const fontFamily = String(source.fontFamily || '').trim();
        const role = String(source.role || '');
        const type = String(source.type || 'text');
        const textRaw = normalizeTextContent(
            source.textRaw || source.content || '',
            role !== 'title'
        );

        return {
            id: String(source.id || ('layer_' + Date.now())),
            type: type,
            role: role,
            visible: typeof source.visible === 'undefined' ? true : Boolean(source.visible),
            textRaw: textRaw,
            hrefRaw: String(source.hrefRaw || source.link_url || ''),
            x: clamp(source.x, 0, 10000),
            y: clamp(source.y, 0, 10000),
            width: clamp(source.width, 0, 10000),
            height: clamp(source.height, 0, 10000),
            fontFamily: fontFamily !== '' && fontFamily !== 'default'
                ? fontFamily
                : 'Arial, Helvetica, sans-serif',
            fontSize: clamp(source.fontSize || source.font_size || 18, 0, 300),
            lineHeight: clamp(source.lineHeight || source.line_height || 1.2, 0.6, 4),
            fontWeight: String(source.fontWeight || source.font_weight || ''),
            fontStyle: String(source.fontStyle || source.font_style || ''),
            textAlign: normalizeTextAlign(String(source.textAlign || source.text_align || 'left')),
            color: String(source.color || '#ffffff'),
            shadow: source.shadow || 'none',
            uppercase: Boolean(source.uppercase)
        };
    };

    const normalizeScene = function (scene) {
        const source = scene && typeof scene === 'object' ? scene : {};
        const canvas = source.canvas && typeof source.canvas === 'object' ? source.canvas : {};
        const layers = Array.isArray(source.layers) ? source.layers : [];

        return {
            schemaVersion: Number.isFinite(Number(source.schemaVersion)) ? Number(source.schemaVersion) : defaultScene.schemaVersion,
            canvas: {
                width: clamp(canvas.width, 240, 2400),
                height: clamp(canvas.height, 160, 3200),
                backgroundImage: String(canvas.backgroundImage || '')
            },
            layers: layers.map(normalizeLayer),
            actions: source.actions && typeof source.actions === 'object' ? source.actions : {}
        };
    };

    const parseSceneJson = function (rawValue, fallbackScene) {
        const fallback = normalizeScene(fallbackScene || defaultScene);
        const raw = String(rawValue == null ? '' : rawValue).trim();

        if (raw === '') {
            return fallback;
        }

        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch (error) {
            return fallback;
        }

        let depth = 0;
        while (typeof parsed === 'string' && depth < 3) {
            const inner = String(parsed).trim();
            if (inner === '') {
                return fallback;
            }
            try {
                parsed = JSON.parse(inner);
            } catch (error) {
                return fallback;
            }
            depth += 1;
        }

        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
            return fallback;
        }

        return normalizeScene(parsed);
    };

    const resolveLayer = function (layer, tokenValues) {
        const resolved = Object.assign({}, normalizeLayer(layer));
        resolved.text = resolveTokens(resolved.textRaw, tokenValues);
        resolved.href = resolveTokens(resolved.hrefRaw, tokenValues);

        if (resolved.uppercase) {
            resolved.text = resolved.text.toUpperCase();
        }

        return resolved;
    };

    const resolveScene = function (scene, tokenValues) {
        const normalized = normalizeScene(scene);

        return {
            schemaVersion: normalized.schemaVersion,
            canvas: normalized.canvas,
            actions: normalized.actions,
            layers: normalized.layers.map(function (layer) {
                return resolveLayer(layer, tokenValues);
            })
        };
    };

    const waitForFonts = function () {
        if (typeof document !== 'undefined' && document.fonts && typeof document.fonts.ready === 'object') {
            return document.fonts.ready.catch(function () {
                return null;
            });
        }

        return Promise.resolve();
    };

    const loadImage = function (src) {
        return new Promise(function (resolve, reject) {
            const image = new Image();

            image.onload = function () {
                resolve(image);
            };
            image.onerror = function () {
                reject(new Error('Não foi possível carregar a imagem: ' + src));
            };

            if (/^https?:\/\//i.test(src)) {
                image.crossOrigin = 'anonymous';
            }

            image.src = src;
        });
    };

    const loadButtonTextureImage = function () {
        if (!buttonTexturePromise) {
            buttonTexturePromise = loadImage(emailButtonTextureUrl)
                .catch(function () {
                    return null;
                });
        }

        return buttonTexturePromise;
    };

    const createCanvas = function (width, height) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        return canvas;
    };

    const drawRoundedRect = function (ctx, x, y, width, height, radius) {
        const safeRadius = Math.max(0, Math.min(radius, width / 2, height / 2));

        ctx.beginPath();
        ctx.moveTo(x + safeRadius, y);
        ctx.lineTo(x + width - safeRadius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
        ctx.lineTo(x + width, y + height - safeRadius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
        ctx.lineTo(x + safeRadius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
        ctx.lineTo(x, y + safeRadius);
        ctx.quadraticCurveTo(x, y, x + safeRadius, y);
        ctx.closePath();
    };

    const drawFallbackBackground = function (ctx, width, height) {
        const gradient = ctx.createLinearGradient(0, 0, width, height * 0.45);
        gradient.addColorStop(0, '#42132a');
        gradient.addColorStop(0.6, '#7c1e3f');
        gradient.addColorStop(1, '#ef8a35');

        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
    };

    /* Mesma logica do editor V2: imagem inteira dentro do quadro (sem cortar bordas). */
    const drawContainImage = function (ctx, image, width, height) {
        const scale = Math.min(width / image.width, height / image.height);
        const drawWidth = image.width * scale;
        const drawHeight = image.height * scale;
        const drawX = (width - drawWidth) / 2;
        const drawY = (height - drawHeight) / 2;

        ctx.drawImage(image, drawX, drawY, drawWidth, drawHeight);
    };

    const buildCanvasFont = function (layer) {
        const fontStyle = layer.fontStyle === 'italic' ? 'italic' : 'normal';
        const fontWeight = String(layer.fontWeight || '').trim() !== '' ? String(layer.fontWeight) : '700';
        const fontSize = Math.max(1, layer.fontSize || 18);
        const family = String(layer.fontFamily || 'Arial, Helvetica, sans-serif');

        return fontStyle + ' ' + fontWeight + ' ' + fontSize + 'px ' + family;
    };

    const applyShadow = function (ctx, layer) {
        if (layer.shadow === 'strong') {
            ctx.shadowColor = 'rgba(44, 25, 23, 0.34)';
            ctx.shadowBlur = 18;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 6;
            return;
        }

        if (layer.shadow === 'soft') {
            ctx.shadowColor = 'rgba(44, 25, 23, 0.16)';
            ctx.shadowBlur = 12;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 3;
            return;
        }

        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
    };

    const splitTextLines = function (ctx, text, maxWidth) {
        const paragraphs = String(text || '').split(/\r?\n/);
        const lines = [];

        paragraphs.forEach(function (paragraph) {
            const clean = paragraph.trim();

            if (clean === '') {
                lines.push('');
                return;
            }

            const words = clean.split(/\s+/);
            let currentLine = '';

            words.forEach(function (word) {
                const testLine = currentLine === '' ? word : currentLine + ' ' + word;

                if (ctx.measureText(testLine).width <= maxWidth || currentLine === '') {
                    currentLine = testLine;
                    return;
                }

                lines.push(currentLine);
                currentLine = word;
            });

            if (currentLine !== '') {
                lines.push(currentLine);
            }
        });

        return lines;
    };

    const drawTextLayer = function (ctx, layer) {
        const width = Math.max(20, layer.width || 20);
        const x = layer.x || 0;
        const y = layer.y || 0;
        const text = String(layer.text || '').trim();

        if (text === '') {
            return;
        }

        ctx.save();
        ctx.font = buildCanvasFont(layer);
        ctx.fillStyle = layer.color || '#ffffff';
        ctx.textBaseline = 'top';
        ctx.textAlign = layer.textAlign || 'left';
        applyShadow(ctx, layer);

        const lineHeightPx = Math.max(1, (layer.fontSize || 18) * (layer.lineHeight || 1.2));
        const lines = splitTextLines(ctx, text, width);
        const drawX = layer.textAlign === 'center'
            ? x + (width / 2)
            : (layer.textAlign === 'right' ? x + width : x);

        lines.forEach(function (line, index) {
            ctx.fillText(line, drawX, y + (index * lineHeightPx));
        });

        ctx.restore();
    };

    const drawButtonLayer = function (ctx, layer, textureImage) {
        const width = Math.max(80, layer.width || 80);
        const height = Math.max(38, layer.height || 38);
        const x = layer.x || 0;
        const y = layer.y || 0;
        const radius = Math.min(height / 2, 999);
        const label = String(layer.text || 'Abrir mensagem');
        const fontSize = clamp(layer.fontSize || 24, 12, 96);

        ctx.save();

        ctx.shadowColor = 'rgba(93, 49, 27, 0.22)';
        ctx.shadowBlur = 14;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 6;

        drawRoundedRect(ctx, x, y, width, height, radius);
        ctx.fillStyle = '#b36b3d';
        ctx.fill();
        ctx.restore();

        ctx.save();
        drawRoundedRect(ctx, x, y, width, height, radius);
        ctx.clip();
        if (textureImage) {
            ctx.drawImage(textureImage, x, y, width, height);
        } else {
            ctx.fillStyle = '#b36b3d';
            ctx.fillRect(x, y, width, height);
        }

        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '700 ' + fontSize + 'px "Helvetica Neue", Arial, sans-serif';
        ctx.fillText(label, x + width / 2, y + height / 2);

        ctx.restore();
    };

    const drawHotspotDebug = function (ctx, layer) {
        ctx.save();
        ctx.strokeStyle = 'rgba(255, 206, 84, 0.7)';
        ctx.setLineDash([12, 6]);
        ctx.lineWidth = 2;
        ctx.strokeRect(layer.x || 0, layer.y || 0, Math.max(24, layer.width || 24), Math.max(24, layer.height || 24));
        ctx.restore();
    };

    const renderSceneToCanvas = async function (canvas, sceneInput, options) {
        const config = options && typeof options === 'object' ? options : {};
        const scene = resolveScene(sceneInput, config.tokenValues);
        const width = scene.canvas.width || canvasDefaults.width;
        const height = scene.canvas.height || canvasDefaults.height;
        const ctx = canvas.getContext('2d');

        canvas.width = width;
        canvas.height = height;

        ctx.clearRect(0, 0, width, height);

        if (scene.canvas.backgroundImage) {
            try {
                const background = await loadImage(scene.canvas.backgroundImage);
                drawContainImage(ctx, background, width, height);
            } catch (error) {
                drawFallbackBackground(ctx, width, height);
            }
        } else {
            drawFallbackBackground(ctx, width, height);
        }

        await waitForFonts();
        const buttonTexture = scene.layers.some(function (layer) {
            return layer && layer.visible !== false && layer.type === 'button';
        }) ? await loadButtonTextureImage() : null;

        scene.layers.forEach(function (layer) {
            if (layer.visible === false) {
                return;
            }

            if (layer.type === 'button') {
                drawButtonLayer(ctx, layer, buttonTexture);
                return;
            }

            if (layer.type === 'hotspot') {
                if (config.debugHotspots) {
                    drawHotspotDebug(ctx, layer);
                }
                return;
            }

            drawTextLayer(ctx, layer);
        });

        return {
            scene: scene,
            canvas: canvas,
            width: width,
            height: height
        };
    };

    const renderSceneToNewCanvas = async function (sceneInput, options) {
        const scene = normalizeScene(sceneInput);
        const canvas = createCanvas(scene.canvas.width, scene.canvas.height);

        await renderSceneToCanvas(canvas, sceneInput, options);

        return canvas;
    };

    window.MessageSceneRenderer = Object.freeze({
        canvasDefaults: canvasDefaults,
        previewTokenValues: previewTokenValues,
        clamp: clamp,
        escapeHtml: escapeHtml,
        resolveTokens: resolveTokens,
        resolvePreviewText: function (value) {
            return resolveTokens(value, previewTokenValues);
        },
        normalizeScene: normalizeScene,
        normalizeLayer: normalizeLayer,
        resolveScene: resolveScene,
        parseSceneJson: parseSceneJson,
        renderSceneToCanvas: renderSceneToCanvas,
        renderSceneToNewCanvas: renderSceneToNewCanvas
    });
})(window);
