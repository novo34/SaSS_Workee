# Cómo Crear el Repositorio en GitHub

Este documento explica cómo crear el repositorio en GitHub y conectarlo con tu proyecto local.

## ✅ Estado Actual

- ✅ Git inicializado en el proyecto
- ✅ `.gitignore` creado y configurado
- ✅ Commit inicial realizado con todos los archivos del proyecto

## 📋 Pasos para Crear el Repositorio en GitHub

### Paso 1: Crear el Repositorio en GitHub

1. **Accede a GitHub**
   - Ve a [https://github.com](https://github.com)
   - Inicia sesión con tu cuenta

2. **Crear nuevo repositorio**
   - Haz clic en el botón **"+"** en la esquina superior derecha
   - Selecciona **"New repository"**

3. **Configurar el repositorio**
   - **Repository name**: `SaSS_Workee` (o el nombre que prefieras)
   - **Description**: `SaaS multitenant que conecta Empresas con Temporeras`
   - **Visibility**: 
     - **Private** (recomendado para proyectos privados)
     - **Public** (si quieres que sea público)
   - **NO marques** "Add a README file" (ya tienes uno)
   - **NO marques** "Add .gitignore" (ya tienes uno)
   - **NO marques** "Choose a license" (o elige una si lo deseas)

4. **Crear el repositorio**
   - Haz clic en **"Create repository"**

### Paso 2: Conectar el Repositorio Local con GitHub

Después de crear el repositorio, GitHub te mostrará instrucciones. Usa estas opciones:

#### Opción A: Usando HTTPS (Recomendado para principiantes)

```bash
# Navegar al directorio del proyecto
cd "C:\Program Files\xammp\htdocs\SaSS_Workee"

# Agregar el remoto (reemplaza TU_USUARIO con tu usuario de GitHub)
git remote add origin https://github.com/TU_USUARIO/SaSS_Workee.git

# Cambiar el nombre de la rama principal a 'main' (si GitHub usa 'main')
git branch -M main

# Subir el código a GitHub
git push -u origin main
```

#### Opción B: Usando SSH (Recomendado si ya tienes SSH configurado)

```bash
# Navegar al directorio del proyecto
cd "C:\Program Files\xammp\htdocs\SaSS_Workee"

# Agregar el remoto (reemplaza TU_USUARIO con tu usuario de GitHub)
git remote add origin git@github.com:TU_USUARIO/SaSS_Workee.git

# Cambiar el nombre de la rama principal a 'main' (si GitHub usa 'main')
git branch -M main

# Subir el código a GitHub
git push -u origin main
```

### Paso 3: Verificar la Conexión

Después de hacer push, verifica que todo esté conectado:

```bash
# Ver los remotos configurados
git remote -v

# Ver el estado
git status
```

## 🔐 Autenticación con GitHub

### Si usas HTTPS:

GitHub ya no acepta contraseñas para autenticación HTTPS. Tienes dos opciones:

#### Opción 1: Personal Access Token (PAT) - Recomendado

1. **Crear un Personal Access Token**:
   - Ve a GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Haz clic en **"Generate new token (classic)"**
   - Dale un nombre descriptivo (ej: "Workee Local Dev")
   - Selecciona los scopes: `repo` (acceso completo a repositorios)
   - Haz clic en **"Generate token"**
   - **COPIA EL TOKEN** (solo se muestra una vez)

2. **Usar el token**:
   - Cuando Git te pida usuario/contraseña:
     - Usuario: tu usuario de GitHub
     - Contraseña: el token que copiaste

#### Opción 2: GitHub CLI (gh)

```bash
# Instalar GitHub CLI (si no lo tienes)
# Windows: winget install GitHub.cli

# Autenticarse
gh auth login

# Seguir las instrucciones en pantalla
```

### Si usas SSH:

1. **Generar clave SSH** (si no tienes una):
   ```bash
   ssh-keygen -t ed25519 -C "tu_email@ejemplo.com"
   ```

2. **Agregar la clave a GitHub**:
   - Copia el contenido de `~/.ssh/id_ed25519.pub`
   - Ve a GitHub → Settings → SSH and GPG keys
   - Haz clic en **"New SSH key"**
   - Pega la clave y guarda

## 📝 Comandos Útiles para el Futuro

### Subir cambios a GitHub

```bash
# Ver qué archivos han cambiado
git status

# Agregar archivos al staging
git add .

# O agregar archivos específicos
git add archivo.php

# Hacer commit
git commit -m "Descripción de los cambios"

# Subir a GitHub
git push
```

### Actualizar desde GitHub

```bash
# Descargar cambios del repositorio remoto
git pull
```

### Ver el historial

```bash
# Ver commits recientes
git log --oneline

# Ver cambios en un archivo específico
git log -- archivo.php
```

### Crear una rama nueva

```bash
# Crear y cambiar a una nueva rama
git checkout -b nombre-de-la-rama

# Subir la rama a GitHub
git push -u origin nombre-de-la-rama
```

## ⚠️ Archivos que NO se Subirán

Gracias al `.gitignore`, estos archivos **NO** se subirán a GitHub:

- `.env` (configuración sensible)
- Archivos temporales y logs
- Archivos del sistema operativo
- Archivos de IDE
- Archivos compilados
- Archivos de backup

## 🔒 Seguridad

**IMPORTANTE**: 

- ✅ El archivo `.env` está en `.gitignore` y NO se subirá
- ✅ Las credenciales de base de datos NO están en el repositorio
- ✅ Los archivos sensibles están protegidos

**RECUERDA**: 
- Nunca subas archivos `.env` con credenciales reales
- Si accidentalmente subes algo sensible, elimínalo del historial de Git inmediatamente
- Usa variables de entorno para configuración sensible

## 📚 Recursos Adicionales

- [Documentación oficial de GitHub](https://docs.github.com)
- [Guía de Git](https://git-scm.com/doc)
- [GitHub CLI](https://cli.github.com)

## 🆘 Solución de Problemas

### Error: "remote origin already exists"

```bash
# Eliminar el remoto existente
git remote remove origin

# Agregar el remoto correcto
git remote add origin https://github.com/TU_USUARIO/SaSS_Workee.git
```

### Error: "failed to push some refs"

```bash
# Si hay cambios en GitHub que no tienes localmente
git pull origin main --allow-unrelated-histories

# Luego intenta push de nuevo
git push -u origin main
```

### Error de autenticación HTTPS

- Verifica que estés usando un Personal Access Token, no tu contraseña
- Asegúrate de que el token tenga permisos `repo`

### Error de autenticación SSH

```bash
# Verificar que la clave SSH esté agregada
ssh -T git@github.com

# Si no funciona, verifica la configuración SSH
cat ~/.ssh/config
```

## ✅ Checklist Final

- [ ] Repositorio creado en GitHub
- [ ] Remoto agregado correctamente (`git remote -v`)
- [ ] Código subido exitosamente (`git push`)
- [ ] Verificado en GitHub que todos los archivos están presentes
- [ ] Verificado que `.env` NO está en el repositorio
- [ ] README.md visible en GitHub

---

**¡Listo!** Tu proyecto ahora está en GitHub y puedes colaborar, hacer seguimiento de cambios y mantener un historial completo del desarrollo.

