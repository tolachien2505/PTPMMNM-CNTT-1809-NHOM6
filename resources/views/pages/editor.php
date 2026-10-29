            <div id="view-editor" class="workspace-view <?= $currentView === 'editor' ? 'active' : '' ?>">
                <!-- STATE 1: UPLOAD & TÀI LIỆU MẪU BAN ĐẦU (MẶC ĐỊNH KHI VÀO TRANG) -->
                <div id="editorUploadState" class="editor-upload-state" style="display: flex;">
                    <div class="editor-upload-card">
                        <div class="upload-state-header">
                            <div class="upload-hero-icon">
                                <svg class="svg-icon svg-icon-xl" viewBox="0 0 24 24" style="width: 48px; height: 48px; color: var(--primary);">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="12" y1="18" x2="12" y2="12"/>
                                    <polyline points="9 15 12 12 15 15"/>
                                </svg>
                            </div>
                            <h2 class="upload-state-title">Chỉnh sửa tệp PDF trực tuyến chuyên nghiệp</h2>
                            <p class="upload-state-subtitle">Thêm văn bản, vẽ tự do, ký số điện tử, đóng dấu phê duyệt và sắp xếp trang toàn diện</p>
                        </div>

                        <!-- Dropzone chính -->
                        <div class="upload-dropzone" id="editorHeroDropzone" onclick="document.getElementById('editorHeroFileInput').click()" ondragover="event.preventDefault(); this.classList.add('dragover');" ondragleave="this.classList.remove('dragover');" ondrop="handleHeroFileDrop(event)">
                            <div class="dropzone-cloud-icon">
                                <svg class="svg-icon" style="width: 52px; height: 52px; color: #2563EB;" viewBox="0 0 24 24">
                                    <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/>
                                    <path d="M12 12v9"/>
                                    <path d="m8 16 4-4 4 4"/>
                                </svg>
                            </div>
                            <div class="dropzone-title">Kéo & Thả tệp PDF của bạn vào đây</div>
                            <div class="dropzone-sub">hoặc bấm vào khung để duyệt tệp từ máy tính</div>
                            <button type="button" class="btn-primary btn-upload-hero" onclick="event.stopPropagation(); document.getElementById('editorHeroFileInput').click();">
                                <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span>Chọn tệp PDF tải lên</span>
                            </button>
                            <input type="file" id="editorHeroFileInput" accept="application/pdf" style="display: none;" onchange="handleHeroFileInput(event)">
                            <div class="dropzone-limits">Hỗ trợ PDF tối đa 50 MB • Xử lý bảo mật an toàn 100%</div>
                        </div>
                    </div>
                </div>

                <!-- STATE 2: GIAO DIỆN CHỈNH SỬA TOÀN NĂNG (HIỂN THỊ KHI ĐÃ CÓ TÀI LIỆU) -->
                <div id="editorWorkspaceContainer" class="editor-container-pdfaid" style="display: none;">
                    <!-- Unified Professional Toolbar matching PDFaid (Single Cohesive Container) -->
                    <div class="editor-unified-toolbar" id="editorUnifiedToolbar">
                        <!-- Row 1: Primary Tools Ribbon -->
                        <div class="editor-primary-ribbon">
                            <!-- Left: History Undo/Redo -->
                            <div class="editor-ribbon-group history-group">
                                <button class="ribbon-tool-btn ribbon-history-btn" id="btnEditorUndo" title="Hoàn tác (Ctrl+Z)" onclick="editorUndo()">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                                    <span>Hoàn tác</span>
                                </button>
                                <button class="ribbon-tool-btn ribbon-history-btn" id="btnEditorRedo" title="Làm lại (Ctrl+Y)" onclick="editorRedo()">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                                    <span>Làm lại</span>
                                </button>
                            </div>

                            <div class="editor-ribbon-divider"></div>

                            <!-- Center: Primary Document Tools (Stacked Icon + Label matching PDFaid) -->
                            <div class="editor-ribbon-group tools-group">
                                <button class="ribbon-tool-btn active" data-tool="add-text" onclick="setEditorTool('add-text')" title="Thêm văn bản mới">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                                    <span>Thêm văn bản</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="edit-text" onclick="setEditorTool('edit-text')" title="Chỉnh sửa văn bản có sẵn trong PDF">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    <span>Chỉnh sửa văn bản</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="signature" onclick="openSignatureModal()" title="Tạo hoặc chèn chữ ký">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="m20.5 14.5-8-8-5 5 8 8Z"/><path d="M7.5 11.5 2 17v5h5l5.5-5.5"/><circle cx="17.5" cy="6.5" r="2.5"/></svg>
                                    <span>Ký</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="draw" onclick="setEditorTool('draw')" title="Vẽ tự do nét mực">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                    <span>Vẽ</span>
                                </button>
                                
                                <!-- Shapes Dropdown Menu -->
                                <div class="ribbon-dropdown-wrapper">
                                    <button class="ribbon-tool-btn" data-tool="shape" id="btnShapesDropdown" onclick="setEditorTool('shape')" title="Đường kẻ & Hình học (Nhấn để vẽ đường kẻ, nhấn mũi tên để chọn hình)">
                                        <div class="ribbon-icon-with-caret">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="5" r="2.5"/></svg>
                                            <span onclick="toggleShapesDropdown(event)" class="dropdown-caret-sm" title="Chọn hình học khác">▼</span>
                                        </div>
                                        <span id="currentShapeLabel">Đường kẻ</span>
                                    </button>
                                    <div class="ribbon-sub-menu" id="shapesMenu">
                                        <div class="sub-menu-item" onclick="selectShape('line', 'Đường thẳng')">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="19" x2="19" y2="5"/></svg>
                                            <span>Đường thẳng</span>
                                        </div>
                                        <div class="sub-menu-item" onclick="selectShape('arrow', 'Mũi tên')">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                            <span>Mũi tên</span>
                                        </div>
                                        <div class="sub-menu-item" onclick="selectShape('rect', 'Hình chữ nhật')">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                                            <span>Hình chữ nhật</span>
                                        </div>
                                        <div class="sub-menu-item" onclick="selectShape('circle', 'Hình tròn')">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>Hình tròn</span>
                                        </div>
                                        <div class="sub-menu-item" onclick="selectShape('polygon', 'Đa giác')">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/></svg>
                                            <span>Đa giác</span>
                                        </div>
                                    </div>
                                </div>

                                <button class="ribbon-tool-btn" data-tool="highlight" onclick="setEditorTool('highlight')" title="Tô sáng / Bút nhớ">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="m9 11-6 6v3h3l6-6"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                                    <span>Tô sáng</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="image" onclick="triggerEditorImageUpload()" title="Chèn hình ảnh">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <span>Ảnh</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="stamp" onclick="openStampModal()" title="Con dấu (Đã duyệt, Bản nháp...)">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M5 22h14"/><path d="M19.27 13.73A2.5 2.5 0 0 0 17.5 9h-11A2.5 2.5 0 0 0 4.73 13.73L7 17h10l2.27-3.27z"/><path d="M12 9V2"/></svg>
                                    <span>Con dấu</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="link" onclick="setEditorTool('link')" title="Thêm liên kết">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                    <span>Liên kết</span>
                                </button>
                                <button class="ribbon-tool-btn" data-tool="note" onclick="setEditorTool('note')" title="Thêm ghi chú">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    <span>Ghi chú</span>
                                </button>
                            </div>

                            <div class="editor-ribbon-divider"></div>

                            <!-- Right: Management Actions & CTA Button -->
                            <div class="editor-ribbon-group actions-group">
                                <button class="ribbon-tool-btn ribbon-action-item" id="btnOpenPageManagerModal" onclick="openPageManagerModal()" title="Mở Trình quản lý trang">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                    <span>Quản lý trang</span>
                                </button>
                                <button class="ribbon-tool-btn ribbon-action-item" onclick="printPdfDocument()" title="In ấn tài liệu">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    <span>In</span>
                                </button>
                                <button class="ribbon-tool-btn ribbon-action-item" onclick="toggleEditorSearch()" title="Tìm kiếm trong tài liệu">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <span>Tìm kiếm</span>
                                </button>
                                <button class="ribbon-tool-btn ribbon-action-item file-switch-item" onclick="switchPdfFile()" title="Đổi tệp PDF khác">
                                    <svg class="svg-icon" viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 1 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                                    <span>Đổi tệp</span>
                                </button>
                                
                                <button class="btn-complete-cta" onclick="finishAndSaveDocument()" title="Hoàn thành chỉnh sửa và lưu PDF">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Hoàn thành</span>
                                </button>
                            </div>
                        </div>

                        <!-- Row 2: Contextual Tool Properties Ribbon (Seamlessly connected below Row 1) -->
                        <div class="editor-properties-ribbon" id="editorPropertiesRibbon">
                            <div class="prop-ribbon-left">
                                <!-- Mode 1: Text Properties (Active for add-text & edit-text, 100% match with PDFaid reference) -->
                                <div class="prop-mode-panel" id="propModeText">
                                    <div class="prop-badge-item" title="Đang ở chế độ chỉnh sửa văn bản">
                                        <span class="prop-text-icon">T</span>
                                    </div>

                                    <!-- Quick Color Palette -->
                                    <div class="color-palette">
                                        <button class="color-swatch active" style="--c: #000000;" onclick="setEditorColor('#000000')" title="Đen"></button>
                                        <button class="color-swatch" style="--c: #EF4444;" onclick="setEditorColor('#EF4444')" title="Đỏ"></button>
                                        <button class="color-swatch" style="--c: #3B82F6;" onclick="setEditorColor('#3B82F6')" title="Xanh dương"></button>
                                        <button class="color-swatch" style="--c: #10B981;" onclick="setEditorColor('#10B981')" title="Xanh lá"></button>
                                        <button class="color-swatch" style="--c: #F59E0B;" onclick="setEditorColor('#F59E0B')" title="Vàng cam"></button>
                                        <button class="color-swatch" style="--c: #8B5CF6;" onclick="setEditorColor('#8B5CF6')" title="Tím"></button>
                                        <input type="color" id="editorCustomColorPicker" class="custom-color-picker" title="Chọn màu bất kỳ..." onchange="setEditorColor(this.value)">
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Opacity Selector -->
                                    <div class="prop-control-item" title="Độ trong suốt">
                                        <select id="editorTextOpacity" class="prop-select" onchange="setEditorOpacity(this.value)">
                                            <option value="1.0" selected>100%</option>
                                            <option value="0.75">75%</option>
                                            <option value="0.5">50%</option>
                                            <option value="0.25">25%</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Font Family Selector with TT icon -->
                                    <div class="prop-control-item prop-font-item" title="Phông chữ">
                                        <span class="prop-font-icon">T<small>T</small></span>
                                        <select id="editorFontFamily" class="prop-select font-family-select" onchange="setEditorFontFamily(this.value)">
                                            <option value="Helvetica, Arial, sans-serif" selected>Helvetica</option>
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="'Times New Roman', Times, serif">Times New Roman</option>
                                            <option value="Cambria, 'Times New Roman', serif">Cambria</option>
                                            <option value="'Courier New', Courier, monospace">Courier New</option>
                                            <option value="'Inter', sans-serif">Inter</option>
                                            <option value="'Roboto', sans-serif">Roboto</option>
                                            <option value="Georgia, serif">Georgia</option>
                                        </select>
                                    </div>

                                    <!-- Font Size Selector -->
                                    <div class="prop-control-item" title="Cỡ chữ">
                                        <select id="editorStrokeOrFontSize" class="prop-select font-size-select" onchange="updateStrokeOrFontSize(this.value)">
                                            <option value="9">9</option>
                                            <option value="10">10</option>
                                            <option value="11">11</option>
                                            <option value="12">12</option>
                                            <option value="14">14</option>
                                            <option value="16">16</option>
                                            <option value="18" selected>18</option>
                                            <option value="20">20</option>
                                            <option value="24">24</option>
                                            <option value="28">28</option>
                                            <option value="32">32</option>
                                            <option value="36">36</option>
                                            <option value="48">48</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Bold & Italic (Matching Image 1) -->
                                    <div class="prop-btn-group" title="Định dạng font chữ">
                                        <button type="button" class="prop-icon-btn" id="btnFormatBold" onclick="toggleEditorBold()" title="In đậm (Bold)" style="font-weight: 700; font-size: 0.95rem;">B</button>
                                        <button type="button" class="prop-icon-btn" id="btnFormatItalic" onclick="toggleEditorItalic()" title="In nghiêng (Italic)" style="font-style: italic; font-size: 0.95rem; font-family: serif;">I</button>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Text Alignment: Left, Center, Right -->
                                    <div class="prop-btn-group" title="Căn lề">
                                        <button class="prop-icon-btn active" id="btnAlignLeft" onclick="setEditorTextAlign('left')" title="Căn trái">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                                        </button>
                                        <button class="prop-icon-btn" id="btnAlignCenter" onclick="setEditorTextAlign('center')" title="Căn giữa">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="18" y1="14" x2="6" y2="14"/><line x1="21" y1="18" x2="3" y2="18"/></svg>
                                        </button>
                                        <button class="prop-icon-btn" id="btnAlignRight" onclick="setEditorTextAlign('right')" title="Căn phải">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                                        </button>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Background Cover Color (Màu nền che chữ gốc) -->
                                    <span class="sub-ribbon-label" style="font-size: 0.8rem; color: #64748B; font-weight: 500;">Nền che:</span>
                                    <div class="prop-control-item" title="Màu nền che chữ gốc - Tự động đồng bộ cùng màu nền slide/ảnh để không bị đè vệt trắng">
                                        <select id="editorTextBgColorSelect" class="prop-select" onchange="handleTextBgColorSelect(this.value)" style="min-width: 105px;">
                                            <option value="auto" selected>✨ Tự động hút</option>
                                            <option value="transparent">Trong suốt</option>
                                            <option value="#FFFFFF">Trắng</option>
                                            <option value="#EA580C">Cam slide</option>
                                            <option value="#3B82F6">Xanh dương</option>
                                            <option value="#0F172A">Đen / Tối</option>
                                            <option value="custom">Màu khác...</option>
                                        </select>
                                    </div>
                                    <input type="color" id="editorTextBgCustomColor" class="custom-color-picker" title="Chọn màu nền che bất kỳ..." style="display: none;" onchange="setEditorTextBgColor(this.value)">
                                </div>

                                <!-- Mode 2: Draw / Shape Properties (Matching Image 3) -->
                                <div class="prop-mode-panel" id="propModeShapes" style="display: none;">
                                    <span class="sub-ribbon-label">Màu nét:</span>
                                    <div class="color-palette">
                                        <button class="color-swatch active" style="--c: #000000;" onclick="setEditorColor('#000000')" title="Đen"></button>
                                        <button class="color-swatch" style="--c: #3B82F6;" onclick="setEditorColor('#3B82F6')" title="Xanh dương"></button>
                                        <button class="color-swatch" style="--c: #EF4444;" onclick="setEditorColor('#EF4444')" title="Đỏ"></button>
                                        <button class="color-swatch" style="--c: #10B981;" onclick="setEditorColor('#10B981')" title="Xanh lá"></button>
                                        <button class="color-swatch" style="--c: #F59E0B;" onclick="setEditorColor('#F59E0B')" title="Vàng cam"></button>
                                        <button class="color-swatch" style="--c: #8B5CF6;" onclick="setEditorColor('#8B5CF6')" title="Tím"></button>
                                        <input type="color" id="editorShapeCustomColor" class="custom-color-picker" title="Chọn màu bất kỳ..." onchange="setEditorColor(this.value)">
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Fill Color -->
                                    <span class="sub-ribbon-label">Màu nền:</span>
                                    <div class="prop-control-item" title="Màu nền / Tô màu">
                                        <select id="editorShapeFill" class="prop-select" onchange="setEditorShapeFill(this.value)">
                                            <option value="none" selected>Trong suốt</option>
                                            <option value="rgba(59, 130, 246, 0.2)">Xanh nhạt</option>
                                            <option value="rgba(239, 68, 68, 0.2)">Đỏ nhạt</option>
                                            <option value="rgba(253, 224, 71, 0.35)">Vàng dạ quang</option>
                                            <option value="rgba(16, 185, 129, 0.2)">Xanh lá nhạt</option>
                                            <option value="#FFFFFF">Trắng</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Opacity -->
                                    <div class="prop-control-item" title="Độ trong suốt">
                                        <select id="editorShapeOpacity" class="prop-select" onchange="setEditorOpacity(this.value)">
                                            <option value="1.0" selected>100%</option>
                                            <option value="0.75">75%</option>
                                            <option value="0.5">50%</option>
                                            <option value="0.25">25%</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Stroke Thickness -->
                                    <div class="prop-control-item" title="Độ dày đường nét">
                                        <select id="editorShapeStrokeWidth" class="prop-select" onchange="setEditorStrokeWidth(this.value)">
                                            <option value="1">1 pt (Mảnh)</option>
                                            <option value="2" selected>2 pt (Chuẩn)</option>
                                            <option value="3">3 pt (Vừa)</option>
                                            <option value="5">5 pt (Đậm)</option>
                                            <option value="8">8 pt (Cực đậm)</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Line Style: Solid, Dashed, Dotted (Matching Image 3) -->
                                    <div class="prop-control-item" title="Kiểu đường viền">
                                        <select id="editorLineDashStyle" class="prop-select" onchange="setEditorLineDashStyle(this.value)">
                                            <option value="solid" selected>— Liền nét</option>
                                            <option value="dashed">--- Nét đứt</option>
                                            <option value="dotted">··· Chấm bi</option>
                                        </select>
                                    </div>

                                    <div class="prop-divider"></div>

                                    <!-- Line Arrow Head -->
                                    <div class="prop-control-item" title="Đầu mũi tên">
                                        <select id="editorLineArrowHead" class="prop-select" onchange="setEditorLineArrowHead(this.value)">
                                            <option value="none">Đường thẳng (—)</option>
                                            <option value="arrow-end" selected>Mũi tên (→)</option>
                                            <option value="arrow-both">Mũi tên hai đầu (↔)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mode 3: Vector Icons Properties -->
                                <div class="prop-mode-panel" id="propModeIcons" style="display: none;">
                                    <span class="sub-ribbon-label">Biểu tượng:</span>
                                    <div class="vector-icon-palette">
                                        <button class="vector-icon-btn" onclick="addVectorIcon('chat')" title="Bình luận / Chat">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('flag')" title="Cờ đánh dấu">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('arrow')" title="Mũi tên chỉ dẫn">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('check')" title="Đã duyệt / Hợp lệ">
                                            <svg class="svg-icon svg-icon-sm text-success" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('cross')" title="Hủy / Loại bỏ">
                                            <svg class="svg-icon svg-icon-sm text-danger" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('star')" title="Quan trọng / Sao">
                                            <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('shield')" title="Bảo mật / Xác thực">
                                            <svg class="svg-icon svg-icon-sm text-primary" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('pin')" title="Ghim điểm nhấn">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('bulb')" title="Ý tưởng / Sáng tạo">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/></svg>
                                        </button>
                                        <button class="vector-icon-btn" onclick="addVectorIcon('warning')" title="Cảnh báo / Lưu ý">
                                            <svg class="svg-icon svg-icon-sm text-warning" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 1 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Trash Can (matching Image 3) -->
                            <div class="prop-ribbon-right">
                                <button class="prop-trash-btn" onclick="deleteSelectedEditorElement()" title="Xóa phần tử đang chọn">
                                    <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Main Editor Body with Thumbnails Strip & Viewport -->
                    <div class="editor-main-workspace">
                        <!-- Left Thumbnails Strip -->
                        <aside class="editor-left-strip" id="editorThumbnailsAside">
                            <div class="strip-header">
                                <div class="strip-header-top">
                                    <span class="strip-title">Trang tài liệu</span>
                                    <span class="strip-count" id="stripPageCountBadge">11 trang</span>
                                </div>
                                <div class="strip-add-dropdown-wrapper">
                                    <button class="btn-strip-add" onclick="toggleAddPageDropdown(event)">
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        <span>Thêm trang</span>
                                        <svg class="svg-icon svg-icon-xs" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                    <div class="strip-add-menu" id="stripAddMenu">
                                        <button class="strip-add-option" onclick="insertBlankPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
                                            <span>Trang trắng mới</span>
                                        </button>
                                        <button class="strip-add-option" onclick="triggerImportPdfPage()">
                                            <svg class="svg-icon svg-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span>Nhập từ tệp tin...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden file input for importing PDF into editor -->
                            <input type="file" id="editorFileInput" accept="application/pdf,image/*" style="display: none;" onchange="handleEditorFileImport(event)">
                            <!-- Hidden file input for inserting image annotation onto PDF page -->
                            <input type="file" id="editorAnnotationImageInput" accept="image/*" style="display: none;" onchange="handleEditorAnnotationImageUpload(event)">

                            <!-- Thumbnails Scrollable List -->
                            <div class="strip-thumbnails-list" id="editorThumbnailsList">
                                <!-- Rendered dynamically by JS -->
                            </div>
                        </aside>

                        <!-- Center Canvas Viewport -->
                        <main class="editor-viewport" id="editorViewport" ondragover="event.preventDefault()" ondrop="handleEditorFileDrop(event)">
                                <!-- In-Editor Text Search Bar matching Image 5 -->
                                <div class="editor-search-bar" id="editorSearchBar" style="display: none;">
                                    <div class="search-bar-inner">
                                        <div class="search-input-pill">
                                            <svg class="svg-icon svg-icon-sm search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" id="editorSearchQuery" placeholder="Tìm kiếm trong tài liệu..." oninput="handleSearchQueryInput(this.value)" onkeydown="if(event.key==='Enter') executeEditorSearch(event.shiftKey ? -1 : 1)">
                                            <button type="button" class="btn-search-clear" onclick="clearEditorSearch()" title="Xóa">✕</button>
                                        </div>
                                        <span class="search-match-count" id="searchMatchCount">0 kết quả</span>
                                        <div class="search-nav-group">
                                            <button type="button" class="btn-search-nav" onclick="executeEditorSearch(-1)" title="Kết quả trước">‹</button>
                                            <button type="button" class="btn-search-nav" onclick="executeEditorSearch(1)" title="Kết quả tiếp">›</button>
                                        </div>
                                        <button type="button" class="btn-search-close" onclick="closeEditorSearch()" title="Đóng tìm kiếm">✕</button>
                                    </div>
                                </div>
                                <!-- Floating Vertical Pen Palette (Matching Image 5) -->
                                <div class="floating-pen-rack" id="floatingPenRack" style="display: none;">
                                    <button type="button" class="pen-rack-close" onclick="togglePenRack(false)" title="Đóng bảng bút">✕</button>
                                    <button type="button" class="pen-rack-item active" id="penRackBallpoint" onclick="selectPenType('ballpoint')" title="Bút bi / Nét mực">
                                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                    </button>
                                    <button type="button" class="pen-rack-item" id="penRackHighlighter" onclick="selectPenType('highlighter')" title="Bút dạ quang / Tô sáng">
                                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m9 11-6 6v3h3l6-6"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                                    </button>
                                    <button type="button" class="pen-rack-item" id="penRackEraser" onclick="selectPenType('eraser')" title="Bút tẩy / Xóa nét vẽ">
                                        <svg class="svg-icon" viewBox="0 0 24 24"><path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg>
                                    </button>
                                    <div class="pen-rack-divider"></div>
                                    <div class="pen-rack-thickness-dropdown">
                                        <button type="button" class="pen-rack-item" id="btnPenThickness" onclick="togglePenThicknessMenu(event)" title="Độ dày nét bút">
                                            <svg class="svg-icon" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                                        </button>
                                        <div class="pen-thickness-menu" id="penThicknessMenu">
                                            <div class="thickness-option" onclick="setPenThickness(1)">
                                                <span class="thickness-line" style="height: 1px;"></span>
                                                <span>1 px (Mảnh)</span>
                                            </div>
                                            <div class="thickness-option active" onclick="setPenThickness(2.5)">
                                                <span class="thickness-line" style="height: 2.5px;"></span>
                                                <span>2.5 px (Chuẩn)</span>
                                            </div>
                                            <div class="thickness-option" onclick="setPenThickness(5)">
                                                <span class="thickness-line" style="height: 5px;"></span>
                                                <span>5 px (Đậm)</span>
                                            </div>
                                            <div class="thickness-option" onclick="setPenThickness(12)">
                                                <span class="thickness-line" style="height: 10px;"></span>
                                                <span>12 px (Bút dạ)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- The Real Paper Sheet with PDF.js Canvas + Annotation Layer -->
                                <div class="pdf-paper-page" id="pdfPaperPage" style="transform: scale(1.0); transform-origin: top center;">
                                    <div class="page-paper-content" id="pagePaperContent">
                                        <!-- True PDF Canvas rendered with pixel-perfect resolution by Mozilla PDF.js -->
                                        <canvas id="pdfRenderCanvas" class="pdf-render-canvas"></canvas>
                                        <!-- Interactive Annotation Layer (Text, Signatures, Stamps, Shapes) -->
                                        <div class="paper-annotation-layer" id="paperAnnotationLayer"></div>
                                        <!-- Text Edit Layer (Detected Text Blocks with Dashed Blue Outlines matching Image 1) -->
                                        <div class="paper-text-edit-layer" id="paperTextEditLayer" style="display: none;"></div>
                                        <!-- Real-time Search Highlight Layer (Yellow Rectangles matching Image 5) -->
                                        <div class="paper-search-highlight-layer" id="paperSearchHighlightLayer"></div>
                                        <!-- Freehand Drawing & Highlighting Canvas Layer -->
                                        <canvas id="paperDrawingCanvas" class="paper-drawing-canvas"></canvas>
                                    </div>
                                </div>

                                <!-- Loading spinner overlay for PDF rendering -->
                                <div id="pdfLoadingOverlay" class="pdf-loading-overlay" style="display: none;">
                                    <div class="pdf-spinner"></div>
                                    <div class="pdf-loading-text" id="pdfLoadingText">Đang giải mã và hiển thị trang PDF...</div>
                                </div>

                            <!-- Floating Bottom Action Bar matching pdfaid -->
                            <div class="editor-floating-bar">
                                <div class="floating-group page-jump">
                                    <span class="floating-label">Trang:</span>
                                    <button class="floating-btn" onclick="jumpEditorPage(-1)" title="Trang trước">▲</button>
                                    <span class="floating-page-info" id="floatingPageInfo">1 / 11</span>
                                    <button class="floating-btn" onclick="jumpEditorPage(1)" title="Trang tiếp">▼</button>
                                </div>

                                <div class="floating-divider"></div>

                                <div class="floating-group zoom-group">
                                    <button class="floating-btn" onclick="zoomEditor(-0.1)" title="Thu nhỏ (Ctrl -)">−</button>
                                    <span class="floating-zoom-level" id="floatingZoomLevel" onclick="resetEditorZoom()" title="Bấm để đặt lại 100%">100%</span>
                                    <button class="floating-btn" onclick="zoomEditor(0.1)" title="Phóng to (Ctrl +)">+</button>
                                </div>

                                <div class="floating-divider"></div>

                                <div class="floating-group">
                                    <button class="floating-btn" id="btnEditorPan" onclick="toggleEditorPan()" title="Công cụ bàn tay di chuyển (Pan)">✋</button>
                                    <button class="floating-btn" onclick="fitEditorToScreen()" title="Vừa chiều rộng màn hình">⛶</button>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
            </div>

            <!-- VIEW: NÂNG CẤP GÓI (UPGRADE) -->
