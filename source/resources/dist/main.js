(function () {
    // Markup from the panel's config, set by the layout
    var icons = {}
    try { icons = JSON.parse(document.body.dataset.yurbaIcons || '{}') } catch (e) {}

    function iconNode(html) {
        var box = document.createElement('span')
        box.innerHTML = html || ''
        return box.childElementCount == 1 ? box.firstElementChild : box
    }

    function ready(fn) {
        if (document.readyState == 'loading') document.addEventListener('DOMContentLoaded', fn)
        else fn()
    }

    function initTabs() {
        document.querySelectorAll('.y-tabs').forEach(function (bar) {
            var scope = bar.closest('form') || document
            var buttons = Array.prototype.slice.call(bar.querySelectorAll('[data-tab-target]'))
            bar.setAttribute('role', 'tablist')

            function activate(btn, focus) {
                var id = btn.getAttribute('data-tab-target')
                buttons.forEach(function (b) {
                    var on = b == btn
                    b.classList.toggle('is-active', on)
                    b.setAttribute('aria-selected', on ? 'true' : 'false')
                    b.tabIndex = on ? 0 : -1
                })
                scope.querySelectorAll('.y-tabs__panel').forEach(function (p) { p.classList.toggle('is-active', p.id == id) })
                if (focus) btn.focus()
            }

            buttons.forEach(function (btn, i) {
                var id = btn.getAttribute('data-tab-target')
                if (!btn.id) btn.id = id + '-tab'
                btn.setAttribute('role', 'tab')
                btn.setAttribute('aria-controls', id)
                btn.setAttribute('aria-selected', btn.classList.contains('is-active') ? 'true' : 'false')
                btn.tabIndex = btn.classList.contains('is-active') ? 0 : -1
                var panel = document.getElementById(id)
                if (panel) {
                    panel.setAttribute('role', 'tabpanel')
                    panel.setAttribute('aria-labelledby', btn.id)
                }
                btn.addEventListener('click', function () { activate(btn, false) })
                btn.addEventListener('keydown', function (e) {
                    var to = null
                    if (e.key == 'ArrowRight') to = buttons[(i + 1) % buttons.length]
                    else if (e.key == 'ArrowLeft') to = buttons[(i - 1 + buttons.length) % buttons.length]
                    else if (e.key == 'Home') to = buttons[0]
                    else if (e.key == 'End') to = buttons[buttons.length - 1]
                    if (!to) return
                    e.preventDefault()
                    activate(to, true)
                })
            })
        })
    }

    function initConditionalFields() {
        var conds = Array.prototype.slice.call(document.querySelectorAll('[data-when-field]'))
        if (!conds.length) return

        function valueOf(name) {
            var els = document.getElementsByName(name)
            if (!els.length) return null
            var list = Array.prototype.slice.call(els)
            var boxes = list.filter(function (e) { return e.type == 'checkbox' })
            if (boxes.length) return boxes[boxes.length - 1].checked ? '1' : '0'
            var radios = list.filter(function (e) { return e.type == 'radio' })
            if (radios.length) {
                for (var i = 0; i < radios.length; i++) if (radios[i].checked) return radios[i].value
                return null
            }
            return list[0].value
        }
        function apply() {
            conds.forEach(function (w) {
                var vals = JSON.parse(w.getAttribute('data-when-values') || '[]')
                var v = valueOf(w.getAttribute('data-when-field'))
                w.style.display = vals.indexOf(v == null ? '' : String(v)) != -1 ? '' : 'none'
            })
        }
        var names = {}
        conds.forEach(function (w) { names[w.getAttribute('data-when-field')] = true })
        Object.keys(names).forEach(function (name) {
            Array.prototype.slice.call(document.getElementsByName(name)).forEach(function (el) {
                el.addEventListener('change', apply)
                el.addEventListener('input', apply)
            })
        })
        apply()
    }

    function initBulk() {
        var form = document.getElementById('y-bulk-form')
        if (!form) return
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.y-row-check'))
        var all = document.getElementById('y-check-all')
        var count = form.querySelector('.y-bulkbar__count')
        var actionInput = form.querySelector('[name="action"]')

        function selected() { return boxes.filter(function (b) { return b.checked }) }
        function sync() {
            var n = selected().length
            count.textContent = n + ' selected'
            form.hidden = n == 0
            if (all) all.checked = n > 0 && n == boxes.length
        }
        boxes.forEach(function (b) { b.addEventListener('change', sync) })
        if (all) all.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = all.checked })
            sync()
        })
        form.querySelectorAll('[data-bulk]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.dataset.bulk == 'delete' && !confirm('Delete the selected rows?')) {
                    e.preventDefault()
                    return
                }
                actionInput.value = btn.dataset.bulk
            })
        })
    }

    function initFilterPanel() {
        var bar = document.querySelector('.y-listbar')
        if (!bar) return
        var trigger = bar.querySelector('[data-filter-toggle]')
        var panel = bar.querySelector('[data-filter-panel]')
        if (!trigger || !panel) return
        var uiOn = bar.getAttribute('data-ui') == '1'
        var active = panel.getAttribute('data-active') && panel.getAttribute('data-active') != '0'

        if (uiOn && window.YurbaUI && window.YurbaUI.Dropdown) {
            try {
                panel.hidden = false
                var dd = new YurbaUI.Dropdown([], {
                    trigger: trigger.innerHTML,
                    triggerClass: trigger.className,
                    content: panel,
                    align: 'right',
                    keepMounted: true
                })
                trigger.parentNode.insertBefore(dd.render(), trigger)
                trigger.remove()
                return
            } catch (e) { panel.hidden = true }
        }

        function open() { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true') }
        function close() { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false') }
        trigger.addEventListener('click', function (e) { e.stopPropagation(); panel.hidden ? open() : close() })
        document.addEventListener('keydown', function (e) { if (e.key == 'Escape' && !panel.hidden) close() })
        if (active) open()
    }

    function initSlug() {
        document.querySelectorAll('input[data-slug-base]').forEach(function (input) {
            var wrap = input.closest('.y-field')
            var preview = wrap && wrap.querySelector('[data-slug-preview]')
            if (!preview) return
            var link = preview.querySelector('[data-slug-link]')
            var base = input.getAttribute('data-slug-base') || ''

            function slugify(v) {
                return v.toString().toLowerCase().trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
            }
            function update() {
                var v = slugify(input.value)
                if (!v) { preview.hidden = true; return }
                var full = base + v
                preview.hidden = false
                link.textContent = full
                link.setAttribute('href', full)
            }
            input.addEventListener('input', update)
            update()
        })
    }

    function initMediaLibrary() {
        var input = document.getElementById('y-media-files')
        var form = document.getElementById('y-media-upload')
        if (input && form) {
            input.addEventListener('change', function () {
                if (input.files.length) form.submit()
            })
        }
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-copy')
                if (navigator.clipboard) navigator.clipboard.writeText(url)
                if (!icons.check || btn.dataset.copied) return
                var prev = btn.innerHTML
                btn.dataset.copied = '1'
                btn.innerHTML = icons.check
                setTimeout(function () {
                    btn.innerHTML = prev
                    delete btn.dataset.copied
                }, 1200)
            })
        })
    }

    function initMediaPicker() {
        var picker = document.getElementById('y-media-picker')
        if (!picker) return
        // re-parent to <body> so a display:none tab can't trap the overlay
        if (picker.parentNode != document.body) document.body.appendChild(picker)
        var grid = picker.querySelector('[data-picker-grid]')
        var searchEl = picker.querySelector('[data-picker-search]')
        var uploadEl = picker.querySelector('[data-picker-upload]')
        var emptyEl = picker.querySelector('[data-picker-empty]')
        var listUrl = picker.getAttribute('data-list-url')
        var storeUrl = picker.getAttribute('data-store-url')
        var meta = document.querySelector('meta[name="csrf-token"]')
        var csrf = meta ? meta.getAttribute('content') : ''
        var activeField = null
        var timer = null
        var body = picker.querySelector('.y-picker__body')
        var uploadLabel = uploadEl.closest('label')
        var page = 1
        var query = ''
        var loading = false
        var hasMore = false
        var generation = 0
        var controller = null
        var returnFocus = null
        var winModal = null
        function winAvailable() { return !!(window.YurbaUI && window.YurbaUI.Modal) }

        function open(fieldEl) {
            activeField = fieldEl
            returnFocus = document.activeElement
            if (winAvailable()) {
                // reuse one modal instance so its re-mounted parts survive hide()'s deferred DOM removal
                if (!winModal) {
                    winModal = new YurbaUI.Modal({ size: 'large', onClose: function () { activeField = null } })
                    winModal.renderComponent(searchEl, 'header')
                    winModal.renderComponent(uploadLabel, 'controls')
                    winModal.renderComponent(body, 'body')
                    // in the modal the scroller is .y-win__body, not our body
                    winModal.addSetupHook(function (modalEl) {
                        var wb = modalEl.querySelector('.y-win__body')
                        if (wb) wb.addEventListener('scroll', maybeLoadMore)
                    })
                }
                winModal.show()
            } else {
                picker.hidden = false
            }
            searchEl.value = ''
            searchEl.focus()
            load('')
        }
        function close() {
            var wasFallback = !picker.hidden
            if (winModal && winModal.isShowed()) winModal.hide()
            picker.hidden = true
            activeField = null
            if (wasFallback && returnFocus && document.contains(returnFocus)) returnFocus.focus()
            returnFocus = null
        }

        // the plain overlay (no YurbaUI.Modal) keeps Tab inside itself like a dialog
        function trapFocus(e) {
            var items = Array.prototype.slice.call(picker.querySelectorAll('input, button, [tabindex]:not([tabindex="-1"])'))
                .filter(function (el) { return !el.disabled && el.getClientRects().length })
            if (!items.length) return
            var first = items[0]
            var last = items[items.length - 1]
            if (!picker.contains(document.activeElement) || (e.shiftKey && document.activeElement == first) || (!e.shiftKey && document.activeElement == last)) {
                e.preventDefault()
                ;(e.shiftKey ? last : first).focus()
            }
        }

        function select(item) {
            if (!activeField) return
            var inputEl = activeField.querySelector('[data-media-input]')
            inputEl.value = item.url
            var prev = activeField.querySelector('[data-media-preview]')
            prev.classList.remove('is-empty')
            prev.replaceChildren(previewNode(item.is_image, item.url, false))
            if (item.is_image && window.YurbaViewer) window.YurbaViewer.bind(prev.querySelector('img'))
            var clr = activeField.querySelector('[data-media-clear]')
            if (clr) clr.hidden = false
            close()
        }

        // urls come from stored file names, so they are set as properties, never parsed as html
        function previewNode(isImage, url, lazy) {
            if (!isImage) return iconNode(icons.description)
            var img = document.createElement('img')
            img.alt = ''
            if (lazy) img.loading = 'lazy'
            img.src = url
            return img
        }

        function makeCell(it) {
            var cell = document.createElement('button')
            cell.type = 'button'
            cell.className = 'y-picker__item'
            cell.title = it.name
            cell.appendChild(previewNode(it.is_image, it.thumb || it.url, true))
            cell.addEventListener('click', function () { select(it) })
            return cell
        }

        function fetchPage() {
            if (loading || (page > 1 && !hasMore)) return
            loading = true
            // a newer search bumps the generation; answers to older ones are dropped
            var mine = generation
            fetch(listUrl + '?q=' + encodeURIComponent(query) + '&page=' + page, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status)
                    return r.json()
                })
                .then(function (d) {
                    if (mine != generation) return
                    var items = (d && d.items) || []
                    if (page == 1) {
                        grid.innerHTML = ''
                        emptyEl.textContent = 'No media found.'
                        emptyEl.hidden = items.length > 0
                    }
                    items.forEach(function (it) { grid.appendChild(makeCell(it)) })
                    hasMore = !!(d && d.has_more)
                    loading = false
                    maybeLoadMore()
                })
                .catch(function () {
                    if (mine != generation) return
                    loading = false
                    hasMore = false
                    emptyEl.textContent = 'Could not load media. Reload the page and try again.'
                    emptyEl.hidden = false
                })
        }

        function scrollEl() {
            return body.closest('.y-win__body') || body
        }

        function maybeLoadMore() {
            if (!hasMore || loading || !body) return
            var s = scrollEl()
            if (s.scrollHeight - s.scrollTop - s.clientHeight < 240) {
                page++
                fetchPage()
            }
        }

        function load(q) {
            generation++
            if (controller) controller.abort()
            controller = window.AbortController ? new AbortController() : null
            loading = false
            query = q
            page = 1
            hasMore = false
            grid.innerHTML = ''
            emptyEl.textContent = 'Loading…'
            emptyEl.hidden = false
            fetchPage()
        }

        if (body) body.addEventListener('scroll', maybeLoadMore)

        searchEl.addEventListener('input', function () {
            clearTimeout(timer)
            var q = searchEl.value.trim()
            timer = setTimeout(function () { load(q) }, 200)
        })

        uploadEl.addEventListener('change', function () {
            if (!uploadEl.files.length) return
            var fd = new FormData()
            for (var i = 0; i < uploadEl.files.length; i++) fd.append('files[]', uploadEl.files[i])
            fd.append('_token', csrf)
            fetch(storeUrl, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) {
                    return r.json().catch(function () { return {} }).then(function (d) { return { ok: r.ok, d: d } })
                })
                .then(function (res) {
                    uploadEl.value = ''
                    var d = res.d
                    if (!res.ok) {
                        alert((d && d.message) || 'Upload failed.')
                        return
                    }
                    if (d && d.items && d.items[0]) select(d.items[0])
                    else load(searchEl.value.trim())
                })
                .catch(function () {
                    uploadEl.value = ''
                    alert('Upload failed.')
                })
        })

        document.addEventListener('click', function (e) {
            var t = e.target
            var openBtn = t.closest ? t.closest('[data-media-open]') : null
            if (openBtn) { e.preventDefault(); open(openBtn.closest('[data-media-field]')); return }
            var clearBtn = t.closest ? t.closest('[data-media-clear]') : null
            if (clearBtn) {
                e.preventDefault()
                var f = clearBtn.closest('[data-media-field]')
                var fInput = f.querySelector('[data-media-input]')
                fInput.value = ''
                var prev = f.querySelector('[data-media-preview]')
                prev.classList.add('is-empty')
                prev.innerHTML = ''
                clearBtn.hidden = true
                return
            }
            if (t.closest && t.closest('[data-picker-close]')) close()
        })
        document.addEventListener('keydown', function (e) {
            if (e.key == 'Tab' && !picker.hidden) { trapFocus(e); return }
            if (e.key != 'Escape') return
            if ((winModal && winModal.isShowed()) || !picker.hidden) close()
        })
    }

    function initRepeater() {
        if (!document.querySelector('[data-repeater]')) return

        function add(root) {
            var body = root.querySelector('[data-repeater-body]')
            var tpl = root.querySelector('[data-repeater-template]')
            // past the highest index in use: the row count repeats an index once a row is removed, and same-named inputs overwrite each other
            var next = 0
            body.querySelectorAll('[name]').forEach(function (el) {
                var m = /^[^\[]+\[(\d+)\]/.exec(el.name)
                if (m) next = Math.max(next, parseInt(m[1], 10) + 1)
            })
            var html = tpl.innerHTML.replace(/__i__/g, String(next))
            // a <tr> only parses inside a table context
            var holder = document.createElement(body.tagName == 'TBODY' ? 'tbody' : 'div')
            holder.innerHTML = html
            var row = holder.firstElementChild
            body.appendChild(row)
            initEditor(row)
        }
        document.addEventListener('click', function (e) {
            var addBtn = e.target.closest('[data-repeater-add]')
            if (addBtn) { e.preventDefault(); add(addBtn.closest('[data-repeater]')); return }
            var rmBtn = e.target.closest('[data-repeater-remove]')
            if (rmBtn) { e.preventDefault(); var row = rmBtn.closest('.y-repeater__item, tr'); if (row) row.remove() }
        })
    }

    function initEditor(root) {
        if (!window.YurbaEditor || !window.YurbaEditor.create) return
        var nodes = (root || document).querySelectorAll('textarea[data-yurba-editor]')
        nodes.forEach(function (el) {
            if (el.dataset.yeReady == '1') return
            el.dataset.yeReady = '1'
            var opts = { field: el }
            if (el.dataset.toolbar) opts.toolbar = el.dataset.toolbar.split(/[\s,]+/).filter(Boolean)
            if (el.dataset.placeholder) opts.placeholder = el.dataset.placeholder
            if (el.dataset.minHeight) opts.minHeight = parseInt(el.dataset.minHeight, 10)
            if (el.dataset.maxChars) opts.maxChars = parseInt(el.dataset.maxChars, 10)
            if (el.dataset.uploadUrl) {
                opts.uploadUrl = el.dataset.uploadUrl
                if (el.dataset.uploadField) opts.uploadField = el.dataset.uploadField
                if (el.dataset.uploadCsrf) opts.uploadHeaders = { 'X-CSRF-TOKEN': el.dataset.uploadCsrf }
                if (el.dataset.maxImageKb) opts.maxImageKb = parseInt(el.dataset.maxImageKb, 10)
            }
            if (icons.editor && Object.keys(icons.editor).length) opts.icons = icons.editor
            YurbaEditor.create(opts)
        })
    }

    function initSelect() {
        if (!window.YurbaUI || !window.YurbaUI.Select) return
        document.querySelectorAll('select[data-yurba-select]').forEach(enhanceSelect)
    }
    function enhanceSelect(sel) {
        if (sel.dataset.yeSelReady == '1') return
        sel.dataset.yeSelReady = '1'
        var opts = []
        for (var i = 0; i < sel.options.length; i++) opts.push({ value: sel.options[i].value, label: sel.options[i].text })
        // option text is decoded data (related titles, user names), so it must stay text
        var ui = new YurbaUI.Select(opts, { value: sel.value, placeholder: '-', html: false })
        sel.style.display = 'none'
        sel.parentNode.insertBefore(ui.render(), sel.nextSibling)
        ui.onChange(function (v) {
            sel.value = v
            sel.dispatchEvent(new Event('change', { bubbles: true }))
        })
    }

    function initViewer() {
        var Viewer = customElements.get('yurba-pv')
        if (!Viewer) return
        var viewer = Viewer.create({ icons: icons.viewer || {} })
        var id = 1
        function bindImg(img) {
            if (!img || img.dataset.ypv) return
            var url = img.getAttribute('data-full') || img.currentSrc || img.getAttribute('src')
            if (!url) return
            // one shared gallery so the arrows walk between every preview
            img.dataset.ypv = 'panel'
            img.dataset.id = String(id++)
            img.dataset.url = url
            if (img.alt) img.dataset.caption = img.alt
            img.style.cursor = 'pointer'
            viewer.bind(img)
        }
        document.querySelectorAll('.y-thumb, .y-image__preview img, .y-media-card__preview img, .y-media-field__preview img').forEach(bindImg)
        window.YurbaViewer = { bind: bindImg }
    }

    ready(function () {
        initTabs()
        initConditionalFields()
        initBulk()
        initFilterPanel()
        initSlug()
        initMediaLibrary()
        initMediaPicker()
        initRepeater()
        initEditor()
        initSelect()
        initViewer()
        initReorder()
        initSidebarScroll()
    })

    function initSidebarScroll() {
        var sidebar = document.querySelector('.y-sidebar')
        if (!sidebar) return
        var key = 'yurba:sidebar-scroll'
        var saved = sessionStorage.getItem(key)
        if (saved != null) sidebar.scrollTop = parseInt(saved, 10) || 0
        function save() { sessionStorage.setItem(key, String(sidebar.scrollTop)) }
        sidebar.addEventListener('scroll', save)
        window.addEventListener('beforeunload', save)
    }

    function initReorder() {
        document.querySelectorAll('tbody[data-reorder-url]').forEach(function (tbody) {
            if (tbody.dataset.reorderReady == '1') return
            tbody.dataset.reorderReady = '1'
            var url = tbody.getAttribute('data-reorder-url')
            var dragging = null
            var before = null

            function rows() { return Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]')) }

            function save(snapshot) {
                var ids = rows().map(function (tr) { return tr.getAttribute('data-id') })
                if (snapshot.join(',') == ids.join(',')) return
                var byId = {}
                rows().forEach(function (tr) { byId[tr.getAttribute('data-id')] = tr })
                function rollback() {
                    snapshot.forEach(function (id) { if (byId[id]) tbody.appendChild(byId[id]) })
                    alert('The new order could not be saved. Reload the page and try again.')
                }
                var meta = document.querySelector('meta[name="csrf-token"]')
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ ids: ids })
                })
                    .then(function (r) { if (!r.ok) rollback() })
                    .catch(rollback)
            }

            // keyboard alternative to drag and drop: the handle is a button, arrows move the row
            rows().forEach(function (tr) {
                var handle = tr.querySelector('.y-drag')
                if (!handle) return
                handle.setAttribute('role', 'button')
                handle.setAttribute('tabindex', '0')
                handle.removeAttribute('aria-hidden')
                handle.setAttribute('aria-label', 'Move row (arrow up / arrow down)')
            })
            tbody.addEventListener('keydown', function (e) {
                if (e.key != 'ArrowUp' && e.key != 'ArrowDown') return
                var handle = e.target.closest ? e.target.closest('.y-drag') : null
                if (!handle) return
                var tr = handle.closest('tr[data-id]')
                var sibling = e.key == 'ArrowUp' ? tr.previousElementSibling : tr.nextElementSibling
                if (!sibling || !sibling.hasAttribute('data-id')) return
                e.preventDefault()
                var snapshot = rows().map(function (r) { return r.getAttribute('data-id') })
                tbody.insertBefore(tr, e.key == 'ArrowUp' ? sibling : sibling.nextSibling)
                handle.focus()
                save(snapshot)
            })

            tbody.addEventListener('dragstart', function (e) {
                var tr = e.target.closest('tr[draggable]')
                if (!tr) return
                dragging = tr
                before = rows().map(function (r) { return r.getAttribute('data-id') })
                tr.classList.add('is-dragging')
                e.dataTransfer.effectAllowed = 'move'
            })

            tbody.addEventListener('dragover', function (e) {
                if (!dragging) return
                e.preventDefault()
                var over = e.target.closest('tr[draggable]')
                if (!over || over == dragging) return
                var rect = over.getBoundingClientRect()
                var after = (e.clientY - rect.top) / rect.height > 0.5
                tbody.insertBefore(dragging, after ? over.nextSibling : over)
            })

            tbody.addEventListener('drop', function (e) { e.preventDefault() })

            tbody.addEventListener('dragend', function () {
                if (!dragging) return
                dragging.classList.remove('is-dragging')
                dragging = null
                save(before || [])
                before = null
            })
        })
    }
})()
