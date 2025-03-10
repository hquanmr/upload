let currentTaskWs = null;
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

// 文件选择处理
document.getElementById('excelFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    // 文件类型验证
    if (!file.name.match(/\.(xlsx|xls)$/i)) {
        showError('请选择Excel文件(.xlsx或.xls格式)');
        this.value = '';
        return;
    }

    // 文件大小验证
    if (file.size > MAX_FILE_SIZE) {
        showError(`文件大小不能超过${MAX_FILE_SIZE/1024/1024}MB`);
        this.value = '';
        return;
    }

    // 显示文件预览
    document.getElementById('selectedFileName').textContent = file.name;
    const preview = document.getElementById('filePreview');
    preview.style.display = 'block';
    preview.innerHTML = `
        <p>文件名：${escapeHtml(file.name)}</p>
        <p>大小：${(file.size/1024).toFixed(2)}KB</p>
        <p>类型：${file.type || '未知'}</p>
    `;
    clearError();
});

// 页面加载时获取上传记录
document.addEventListener('DOMContentLoaded', fetchRecords);

// 表单提交处理
document.getElementById('uploadForm').addEventListener('submit', upload);

async function fetchRecords() {
    const loadingEl = document.getElementById('recordsLoading');
    loadingEl.style.display = 'block';
    try {
        const res = await fetch('/records');
        if (!res.ok) throw new Error('获取上传记录失败');
        const data = await res.json();
        if (data.code === 200) {
            renderRecords(data.data);
        } else {
            throw new Error(data.message || '获取记录失败');
        }
    } catch (error) {
        showError(`获取上传记录失败: ${error.message}`);
    } finally {
        loadingEl.style.display = 'none';
    }
}

function renderRecords(records) {
    const tbody = document.querySelector('#recordsTable tbody');
    if (!records || records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">暂无记录</td></tr>';
        return;
    }

    tbody.innerHTML = records.map(record => `
        <tr>
            <td>${escapeHtml(record.file_name)}</td>
            <td>
                <span class="status-badge ${record.status}">
                    ${getStatusText(record.status)}
                </span>
            </td>
            <td>${formatDate(record.created_at)}</td>
            <td>
                ${record.status === 'processing' ?
                `<button onclick="showProgressModal('${escapeHtml(record.task_id)}')">查看进度</button>` :
                record.status === 'completed' ?
                `<button onclick="downloadFile('')">下载</button>` : ''
                }
            </td>
        </tr>
    `).join('');
}

function upload(event) {
    event.preventDefault();
    const form = document.getElementById('uploadForm');
    const formData = new FormData(form);
    const uploadButton = document.getElementById('uploadButton');

    // 表单验证
    const userId = formData.get('userId');
    const goodsId = formData.get('goodsId');
    const file = formData.get('excelFile');

    if (!file || !file.name) {
        showError('请选择要上传的文件');
        return;
    }
    if (!userId) {
        showError('请输入用户ID');
        return;
    }
    if (!goodsId) {
        showError('请输入商品ID');
        return;
    }

    uploadButton.disabled = true;
    uploadButton.innerHTML = '<span class="loading"></span> 上传中...';
    clearError();

    fetch('http://upload.test/upload', {
        method: 'POST',
        body: formData,
    })
    .then(res => res.json())
    .then(data => {
        if (data.code === 200 && data.data?.taskId) {
            showSuccess('文件上传成功');
            form.reset();
            document.getElementById('selectedFileName').textContent = '';
            document.getElementById('filePreview').style.display = 'none';
            fetchRecords();
            showProgressModal(data.data.taskId);
        } else {
            throw new Error(data.message || '上传失败');
        }
    })
    .catch(error => {
        showError(`上传失败: ${error.message}`);
    })
    .finally(() => {
        uploadButton.disabled = false;
        uploadButton.textContent = '开始上传';
    });
}

function showProgressModal(taskId) {
    const progressModal = document.getElementById('progressModal');
    const progressBar = document.querySelector('#progressModal .progress');
    const progressDetails = document.getElementById('progressDetails');
    
    progressModal.style.display = 'block';
    progressBar.style.width = '0%';
    progressDetails.textContent = '正在连接...';

    // 关闭之前的WebSocket连接
    if (currentTaskWs) {
        currentTaskWs.close();
        currentTaskWs = null;
    }

    // 创建新的WebSocket连接
    currentTaskWs = new WebSocket(`ws://upload.test:2346`);
    
    currentTaskWs.onopen = () => {
        currentTaskWs.send(JSON.stringify({ taskId }));
        progressDetails.textContent = '已连接，等待进度更新...';
    };

    currentTaskWs.onmessage = function (event) {
        try {
            const progressData = JSON.parse(event.data);
            progressBar.style.width = `${progressData.progress}%`;
            progressDetails.textContent = `处理进度: ${progressData.progress}%`;
            
            if (progressData.progress >= 100) {
                setTimeout(() => {
                    closeModal();
                    fetchRecords();
                }, 1000);
            }
        } catch (error) {
            console.error('处理进度数据失败:', error);
        }
    };

    currentTaskWs.onerror = function (error) {
        progressDetails.textContent = '连接出错，正在重试...';
        // 3秒后重试连接
        setTimeout(() => {
            if (progressModal.style.display !== 'none') {
                showProgressModal(taskId);
            }
        }, 3000);
    };

    currentTaskWs.onclose = function () {
        currentTaskWs = null;
    };
}

function closeModal() {
    const progressModal = document.getElementById('progressModal');
    progressModal.style.display = 'none';

    if (currentTaskWs) {
        currentTaskWs.close();
        currentTaskWs = null;
    }
}

function showError(message) {
    const errorEl = document.getElementById('errorMessage');
    errorEl.textContent = message;
    errorEl.style.display = 'block';
}

function clearError() {
    const errorEl = document.getElementById('errorMessage');
    errorEl.textContent = '';
    errorEl.style.display = 'none';
}

function showSuccess(message) {
    // 可以根据需要实现成toast或其他提示形式
    alert(message);
}

function getStatusText(status) {
    const statusMap = {
        'pending': '等待处理',
        'processing': '处理中',
        'completed': '已完成',
        'failed': '处理失败'
    };
    return statusMap[status] || status;
}

function formatDate(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function downloadFile(url) {
    if (!url) {
        showError('下载链接无效');
        return;
    }
    window.open(url, '_blank');
}

function escapeHtml(unsafe) {
    console.log(unsafe);
if (typeof unsafe !== 'string') {
console.error('escapeHtml: 输入必须为字符串');
return '';
}
return unsafe
.replace(/&/g, "&amp;")
.replace(/</g, "&lt;")
.replace(/>/g, "&gt;")
.replace(/"/g, "&quot;")
.replace(/'/g, "&#039;");
}