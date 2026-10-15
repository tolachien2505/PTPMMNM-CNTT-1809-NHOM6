/**
 * PDF Manager - Frontend Application Logic
 * Quản lý danh sách trang, thứ tự kéo thả, xoay và Undo/Redo
 */

class PDFEditorState {
    constructor() {
        this.pages = []; // Danh sách các trang hiện tại trong kế hoạch
        this.history = []; // Ngăn xếp lịch sử cho thao tác Undo
        this.selectedPages = new Set();
    }

    // Ghi lại trạng thái trước khi thay đổi (hỗ trợ Undo)
    pushHistory() {
        this.history.push(JSON.stringify(this.pages));
        if (this.history.length > 20) {
            this.history.shift();
        }
    }

    // Hoàn tác thao tác gần nhất (Undo)
    undo() {
        if (this.history.length > 0) {
            this.pages = JSON.parse(this.history.pop());
            this.render();
        }
    }

    // Xoay trang theo góc (90, 180, 270 độ)
    rotatePage(pageIndex, degrees = 90) {
        this.pushHistory();
        if (this.pages[pageIndex]) {
            this.pages[pageIndex].rotation = (this.pages[pageIndex].rotation + degrees) % 360;
            this.render();
        }
    }

    // Đổi thứ tự trang (Move Page)
    movePage(fromIndex, toIndex) {
        this.pushHistory();
        const [movedItem] = this.pages.splice(fromIndex, 1);
        this.pages.splice(toIndex, 0, movedItem);
        this.render();
    }

    // Loại bỏ trang khỏi kế hoạch xuất
    removePage(pageIndex) {
        this.pushHistory();
        this.pages.splice(pageIndex, 1);
        this.render();
    }

    render() {
        console.log("Cập nhật giao diện với danh sách trang:", this.pages);
    }
}

const editorState = new PDFEditorState();

document.addEventListener('DOMContentLoaded', () => {
    console.log("Khởi tạo ứng dụng PDF Manager thành công.");
});
