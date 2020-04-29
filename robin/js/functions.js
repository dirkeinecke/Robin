const openMessageModal = function() {
  $('#messageModal').modal('show');
};

const openMessageModalQuestion = function(element) {
  document.querySelector('#messageModal .modal-title').innerHTML = element.dataset.messagetype;
  document.querySelector('#messageModal .modal-body').innerHTML = element.dataset.messagetext;
  document.querySelector('#messageModal .action-yes').href = element.dataset.url;

  document.querySelector('#messageModal .action-no').classList.remove('d-none');
  document.querySelector('#messageModal .action-yes').classList.remove('d-none');
  document.querySelector('#messageModal .action-close').classList.add('d-none');

  openMessageModal();

  return false;
};