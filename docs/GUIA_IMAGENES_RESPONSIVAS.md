# Guía de Imágenes Responsivas - Workee

## Introducción

Esta guía documenta cómo usar imágenes responsivas en el sistema Workee, siguiendo las mejores prácticas de performance, accesibilidad y responsive design.

## Reglas Obligatorias

### 1. Lazy Loading Obligatorio

**TODAS las imágenes no críticas DEBEN usar `loading="lazy"`:**

```html
<!-- ✅ CORRECTO -->
<img src="image.jpg" alt="Descripción" loading="lazy">

<!-- ❌ INCORRECTO -->
<img src="image.jpg" alt="Descripción">
```

**Excepciones (imágenes críticas):**
- Logo en header (above the fold)
- Hero images (above the fold)
- Imágenes en viewport inicial

### 2. Aspect Ratio Obligatorio

**TODAS las imágenes DEBEN tener `aspect-ratio` definido para evitar CLS:**

```html
<!-- ✅ CORRECTO - Usando clase CSS -->
<img src="image.jpg" alt="Descripción" class="responsive-image" loading="lazy">

<!-- ✅ CORRECTO - Usando atributo data-aspect-ratio -->
<img src="image.jpg" alt="Descripción" data-aspect-ratio="16/9" loading="lazy">

<!-- ✅ CORRECTO - Usando aspect-ratio inline (solo si es necesario) -->
<img src="image.jpg" alt="Descripción" style="aspect-ratio: 16/9;" loading="lazy">
```

### 3. Srcset y Sizes Obligatorios

**Para imágenes que cambian de tamaño según viewport:**

```html
<!-- ✅ CORRECTO -->
<img 
    src="image-400w.jpg" 
    srcset="image-400w.jpg 400w, image-800w.jpg 800w, image-1200w.jpg 1200w"
    sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
    alt="Descripción"
    loading="lazy"
    class="responsive-image"
>
```

### 4. WebP con Fallback

**Usar `<picture>` para WebP con fallback:**

```html
<!-- ✅ CORRECTO -->
<picture>
    <source srcset="image.webp" type="image/webp">
    <source srcset="image.jpg" type="image/jpeg">
    <img 
        src="image.jpg" 
        alt="Descripción"
        loading="lazy"
        class="responsive-image"
    >
</picture>
```

### 5. Texto Alternativo Obligatorio

**TODAS las imágenes DEBEN tener `alt`:**

```html
<!-- ✅ CORRECTO - Imagen informativa -->
<img src="product.jpg" alt="Producto X en color azul">

<!-- ✅ CORRECTO - Imagen decorativa -->
<img src="decoration.jpg" alt="">

<!-- ❌ INCORRECTO -->
<img src="image.jpg">
```

## Clases CSS Disponibles

### Responsive Images

```html
<!-- Imagen 16:9 (landscape común) -->
<img src="image.jpg" alt="..." class="responsive-image" loading="lazy">

<!-- Imagen cuadrada -->
<img src="image.jpg" alt="..." class="responsive-image responsive-image--square" loading="lazy">

<!-- Imagen portrait -->
<img src="image.jpg" alt="..." class="responsive-image responsive-image--portrait" loading="lazy">
```

### Aspect Ratio Utilities

```html
<!-- Aspect ratio 16:9 -->
<img src="image.jpg" alt="..." class="aspect-ratio-16-9" loading="lazy">

<!-- Aspect ratio 4:3 -->
<img src="image.jpg" alt="..." class="aspect-ratio-4-3" loading="lazy">

<!-- Aspect ratio 1:1 (cuadrado) -->
<img src="image.jpg" alt="..." class="aspect-ratio-1-1" loading="lazy">
```

### Object Fit

```html
<!-- Cover (rellena contenedor, puede recortar) -->
<img src="image.jpg" alt="..." class="object-fit-cover" loading="lazy">

<!-- Contain (muestra completa, puede dejar espacios) -->
<img src="image.jpg" alt="..." class="object-fit-contain" loading="lazy">
```

### Avatares

```html
<!-- Avatar tamaño medio (default) -->
<img src="avatar.jpg" alt="Nombre usuario" class="avatar" loading="lazy">

<!-- Avatar pequeño -->
<img src="avatar.jpg" alt="Nombre usuario" class="avatar avatar--sm" loading="lazy">

<!-- Avatar grande -->
<img src="avatar.jpg" alt="Nombre usuario" class="avatar avatar--lg" loading="lazy">
```

### Thumbnails

```html
<!-- Thumbnail cuadrado -->
<img src="thumb.jpg" alt="..." class="thumbnail" loading="lazy">
```

## Ejemplos Completos

### Ejemplo 1: Imagen de Producto Responsive

```html
<picture>
    <source 
        srcset="product-400w.webp 400w, product-800w.webp 800w, product-1200w.webp 1200w" 
        type="image/webp"
        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
    >
    <source 
        srcset="product-400w.jpg 400w, product-800w.jpg 800w, product-1200w.jpg 1200w" 
        type="image/jpeg"
        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
    >
    <img 
        src="product-400w.jpg" 
        alt="Producto X - Descripción detallada"
        loading="lazy"
        class="responsive-image"
    >
</picture>
```

### Ejemplo 2: Avatar de Usuario

```html
<img 
    src="avatar.jpg" 
    alt="Foto de perfil de Juan Pérez"
    class="avatar avatar--lg"
    loading="lazy"
>
```

### Ejemplo 3: Hero Image (Above the Fold - Sin Lazy)

```html
<picture>
    <source srcset="hero.webp" type="image/webp">
    <img 
        src="hero.jpg" 
        alt="Bienvenido a Workee"
        class="responsive-image"
        <!-- NO usar loading="lazy" en hero images -->
    >
</picture>
```

### Ejemplo 4: Galería de Imágenes

```html
<div class="gallery">
    <picture>
        <source srcset="gallery-1.webp" type="image/webp">
        <img 
            src="gallery-1.jpg" 
            alt="Imagen 1 de la galería"
            class="thumbnail"
            loading="lazy"
        >
    </picture>
    <picture>
        <source srcset="gallery-2.webp" type="image/webp">
        <img 
            src="gallery-2.jpg" 
            alt="Imagen 2 de la galería"
            class="thumbnail"
            loading="lazy"
        >
    </picture>
</div>
```

## Tamaños Recomendados

### Breakpoints y Tamaños

- **Mobile (< 768px)**: 400w - 600w
- **Tablet (768px - 1200px)**: 800w - 1000w
- **Desktop (> 1200px)**: 1200w - 1600w

### Formatos

1. **WebP** (preferido): ~30% más pequeño que JPEG
2. **JPEG** (fallback): Para compatibilidad
3. **PNG**: Solo para imágenes con transparencia
4. **SVG**: Para iconos y gráficos vectoriales

## Performance

### Optimización Antes de Subir

1. **Comprimir imágenes**: Usar herramientas como:
   - TinyPNG / TinyJPG
   - ImageOptim
   - Squoosh

2. **Generar múltiples tamaños**: Crear versiones 400w, 800w, 1200w

3. **Convertir a WebP**: Generar versión WebP de cada tamaño

### Lazy Loading con IntersectionObserver

El sistema ya incluye lazy loading automático en `frontend/js/core/performance.js`:

```javascript
// Se activa automáticamente para imágenes con data-src
<img data-src="image.jpg" alt="...">
```

## Accesibilidad

### Reglas de Alt Text

1. **Imágenes informativas**: Descripción clara y específica
2. **Imágenes decorativas**: `alt=""`
3. **Imágenes con texto**: Incluir el texto en el alt
4. **Imágenes complejas**: Descripción detallada o usar `longdesc`

### Ejemplos de Alt Text

```html
<!-- ✅ BUENO -->
<img src="chart.jpg" alt="Gráfico de ventas: Enero 10k, Febrero 15k, Marzo 20k">

<!-- ✅ BUENO - Decorativa -->
<img src="decoration.jpg" alt="">

<!-- ❌ MALO - Genérico -->
<img src="image.jpg" alt="Imagen">

<!-- ❌ MALO - Sin alt -->
<img src="image.jpg">
```

## Verificación

### Checklist Antes de Desplegar

- [ ] Todas las imágenes tienen `alt`
- [ ] Imágenes no críticas tienen `loading="lazy"`
- [ ] Imágenes tienen `aspect-ratio` definido
- [ ] Imágenes grandes usan `srcset` y `sizes`
- [ ] WebP con fallback para imágenes importantes
- [ ] Imágenes comprimidas antes de subir
- [ ] Tamaños apropiados (no imágenes de 4000px para thumbnails)

## Referencias

- [MDN: Responsive Images](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
- [Web.dev: Image Optimization](https://web.dev/fast/#optimize-your-images)
- [WCAG: Images](https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html)

